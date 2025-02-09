<?php

namespace Subhendu\EmbedVector\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;

class JsonlFileGeneratorService
{
    private Filesystem $disk;
    private string $uploadFilesDir;
    private EmbeddableContract $embeddableModel;

    public function __construct(
        private EmbeddingService $embeddingService,
    ) {
        $this->disk = Storage::disk('local');
    }

    public function generate(string $embeddableModelName, string $type, ?int $chunkSize = null): void
    {
        $this->embeddableModel = app($embeddableModelName);
        $this->uploadFilesDir = $this->buildUploadFilesDir($type);
        
        // Clear any existing files in the directory
        if ($this->disk->exists($this->uploadFilesDir)) {
            $this->disk->deleteDirectory($this->uploadFilesDir);
        }
        
        // Create fresh directory
        $this->disk->makeDirectory($this->uploadFilesDir, 0755, true);
        
        $chunkSize = $chunkSize ?? config('embedvector.chunk_size', 500);
        $processedCount = 0;
        $jsonlContent = '';
        $batchCount = 1;

        // Get the query results
        $query = $this->getItemsToEmbedQuery($type);
        
        // If no records to process, create empty file
        if ($query->count() === 0) {
            $this->disk->put($this->getInputFileName(1), '');
            return;
        }

        $query->chunkById($chunkSize, function ($models) use (&$jsonlContent, &$processedCount, &$batchCount) {
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
            
            if (!empty($jsonlContent)) {
                $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
                $jsonlContent = '';
            }
        });

        // Final write if there's any remaining content
        if (!empty($jsonlContent)) {
            $this->disk->put($this->getInputFileName($batchCount), $jsonlContent);
        }
    }

    private function getItemsToEmbedQuery(string $type)
    {
        return $type === 'init' 
            ? $this->embeddableModel->queryForEmbedding()
            : $this->embeddableModel->queryForSyncing();
    }

    private function buildUploadFilesDir(string $type): string
    {
        return config('embedvector.directories.input').'/'.class_basename($this->embeddableModel).'/'.$type;
    }

    public function getInputFileName($uniqueId = 1): string
    {
        return $this->uploadFilesDir."/embeddings_{$uniqueId}.jsonl";
    }

    private function generateJsonLine(EmbeddableContract $model): string
    {
        $data = [
            'custom_id' => $model->getCustomId(),
            'method' => 'POST',
            'url' => '/v1/embeddings',
            'body' => [
                'model' => $this->embeddingService->getEmbeddingModel(),
                'input' => $model->toEmbeddingText(),
            ],
        ];

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
} 