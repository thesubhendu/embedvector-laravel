<?php

namespace Subhendu\EmbedVector\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Models\EmbeddingBatch;

readonly class BatchEmbeddingService
{
    private EmbeddableContract $embeddableModel;

    private Filesystem $disk;

    public string $uploadFilesDir;

    public function __construct(
        string $embeddableModelName,
        private EmbeddingService $embeddingService,
        private EmbeddingBatch $embeddingBatchModel,
        public string $type = 'sync'  // init or sync
    ) {
        $this->disk = Storage::disk('local');
        $this->embeddableModel = app($embeddableModelName);

        $this->uploadFilesDir = config('embedvector.directories.input').'/'.class_basename($this->embeddableModel).'/'.$type;
    }

    private function itemsToEmbedQuery(): Builder
    {
        if ($this->type == 'init') {
            return $this->embeddableModel->queryForEmbedding();
        }
        // by default always sync

        return $this->embeddableModel->queryForSyncing();

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
            'status' => 'validating',
        ]);

        unlink($fileToEmbed);

        return $response;
    }

    public function getInputFileName($uniqueId = 1): string
    {
        return $this->uploadFilesDir."/embeddings_{$uniqueId}.jsonl";
    }

    /**
     * @return void
     *              Generates embedding file(s) to upload to OpenAI
     */
    public function generateJsonLFile(int $chunkSize = null): void
    {
        $chunkSize = $chunkSize ?? config('embedvector.chunk_size', 500);
        $processedCount = 0;
        $jsonlContent = '';
        $batchCount = 1;

        $this->itemsToEmbedQuery()->chunkById($chunkSize, function ($models) use (&$jsonlContent, &$processedCount, &$batchCount) {
            foreach ($models as $model) {
                /** @var \Subhendu\EmbedVector\Contracts\EmbeddableContract $model */
                $jsonlContent .= $this->generateJsonLine($model) . "\n";
                $processedCount++;

                if ($processedCount >= config('embedvector.lot_size')) {
                    $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
                    $jsonlContent = '';
                    $processedCount = 0;
                    $batchCount++;
                }
            }
            // Flush after processing each chunk to capture any remaining lines
            if (! empty($jsonlContent)) {
                $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
                $jsonlContent = '';
            }
        });

        if (! empty($jsonlContent)) {
            $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
        }
    }

    private function generateJsonLine(EmbeddableContract $model): string
    {
        $data = [
            'custom_id' => $model->getCustomId(),
            'method' => 'POST',
            'url' => '/v1/embeddings',
            'body' => [
                'model' => $this->embeddingService->embeddingModel,
                'input' => $model->toEmbeddingText(),
            ],
        ];

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
