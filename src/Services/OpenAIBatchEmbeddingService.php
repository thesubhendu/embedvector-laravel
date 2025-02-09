<?php

namespace Subhendu\EmbedVector\Services;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Subhendu\EmbedVector\Models\EmbeddingBatch;

readonly class OpenAIBatchEmbeddingService
{
    private Filesystem $disk;

    public function __construct(
        private EmbeddingService $embeddingService,
        private EmbeddingBatch $embeddingBatchModel,
        private JsonlFileGeneratorService $jsonlFileGenerator,
    ) {
        $this->disk = Storage::disk('local');
    }

    public function process(string $embeddableModelName, string $type = 'sync'): array
    {
        $embeddableModel = app($embeddableModelName);
        $uploadFilesDir = config('embedvector.directories.input').'/'.class_basename($embeddableModel).'/'.$type;

        // Generate JSONL files
        $this->jsonlFileGenerator->generate($embeddableModelName, $type, 8000);

        // Get all files in the directory
        $files = $this->disk->files($uploadFilesDir);

        if (empty($files)) {
            throw new Exception('No files were generated for batch embedding');
        }

        $results = ['success' => true, 'messages' => []];

        foreach ($files as $file) {
            try {
                $this->uploadFileForBatchEmbedding($file, $embeddableModelName);
                $results['messages'][] = 'File uploaded and batch created successfully! We will process it soon.';
            } catch (Exception $e) {
                $results['success'] = false;
                $results['messages'][] = 'Error while processing file for batch embedding: ' . $e->getMessage();
            }
        }

        return $results;
    }

    private function uploadFileForBatchEmbedding(string $filePath, string $embeddableModelName)
    {
        $client = $this->embeddingService->getClient();
        $fullPath = $this->disk->path($filePath);

        $fileResponse = $client->files()->upload([
            'purpose' => 'batch',
            'file' => fopen($fullPath, 'r'),
        ]);

        $response = $client->batches()->create([
            'input_file_id' => $fileResponse->id,
            'endpoint' => '/v1/embeddings',
            'completion_window' => '24h',
        ]);

        $this->embeddingBatchModel->create([
            'batch_id' => $response->id,
            'input_file_id' => $fileResponse->id,
            'embeddable_model' => $embeddableModelName,
            'status' => 'validating',
        ]);

        return $response;
    }
}
