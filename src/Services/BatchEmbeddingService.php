<?php

namespace Subhendu\Recommender\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Subhendu\Recommender\Contracts\EmbeddableContract;
use Subhendu\Recommender\Models\EmbeddingBatch;

readonly class BatchEmbeddingService
{
    private EmbeddableContract $embeddableModel;

    private Filesystem $disk;

    private const lotSize = 50000; // it is limit, create folder and files to it in chunk of 50k each file

    public const inputFileDirectory = 'embeddings/input';  // using storage local disk , input file that will be uploaded to openAI

    public function __construct(
        string $embeddableModelName,
        private EmbeddingService $embeddingService,
        private EmbeddingBatch $embeddingBatchModel
    ) {
        $this->disk = Storage::disk('local');
        $this->embeddableModel = app($embeddableModelName);

        $this->disk->deleteDirectory(self::inputFileDirectory);
    }

    public function itemsToEmbedQuery(): Builder
    {
        // todo handle efficiently using chunks
        //        $existingEmbeddedJobs = $this->embeddableModel->embeddingStorage()->query()->pluck('model_id')->toArray();

        return $this->embeddableModel->itemsToEmbed();
        //            ->whereNotIn($this->embeddableModel->getKeyName(), $existingEmbeddedJobs)
        //            ->whereIn($this->embeddableModel->getKeyName(), [11, 12, 13, 14, 15, 16, 17, 18, 19, 10]) // todo remove hardcoded ids
    }

    public function uploadFileForBatchEmbedding(string $fileToEmbed)
    {
        $client = $this->embeddingService->getClient();

        $fileResponse = $client->files()->upload(
            parameters: [
                'purpose' => 'batch',
                'file' => fopen($fileToEmbed, 'r'),
            ]
        );

        $fileId = $fileResponse->id;

        $response = $client->batches()->create(
            parameters: [
                'input_file_id' => $fileId,
                'endpoint' => '/v1/embeddings',
                'completion_window' => '24h',
            ]
        );

        $this->embeddingBatchModel->create([
            'batch_id' => $response->id,  // The batch ID from OpenAI
            'input_file_id' => $fileId, // open ai file id on uploaded file
            'embeddable_model' => get_class($this->embeddableModel),
        ]);

        return $response;
    }

    private function getInputFileName($uniqueId = 1): string
    {
        return self::inputFileDirectory."/embeddings_{$uniqueId}.jsonl";
    }

    /**
     * @return void
     *              Generates embedding file(s) to upload to OpenAI
     */
    public function generateJsonLFile(int $chunkSize): void
    {
        $processedCount = 0;
        $jsonlContent = '';
        $batchCount = 1;

        $this->itemsToEmbedQuery()
            ->chunkById($chunkSize, function ($models) use (&$jsonlContent, &$processedCount, &$batchCount) {
                foreach ($models as $model) {
                    $jsonlContent .= $this->generateJsonLine($model)."\n";
                    $processedCount++;

                    if ($processedCount >= self::lotSize) {
                        $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
                        $jsonlContent = '';
                        $processedCount = 0;
                        $batchCount++;
                    }

                    if ($jsonlContent) {
                        $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
                    }
                }
            });

        if ($jsonlContent) {
            $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
        }
    }

    private function generateJsonLine(EmbeddableContract $model): string
    {
        $text = $model->toEmbeddingText();
        $data = [
            'custom_id' => (string) $model->getKey(),
            'method' => 'POST',
            'url' => '/v1/embeddings',
            'body' => [
                'model' => $this->embeddingService->embeddingModel,
                'input' => $text,
            ],

        ];

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
