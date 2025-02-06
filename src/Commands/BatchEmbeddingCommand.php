<?php

namespace Subhendu\EmbedVector\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Subhendu\EmbedVector\Services\BatchEmbeddingService;

class BatchEmbeddingCommand extends Command
{
    protected $signature = 'embedding:batch {modelName} {--type=sync} {--force}';

    protected $description = 'Generate a JSONL file for batch embedding';

    private $disk;

    public function __construct()
    {
        parent::__construct();
        $this->disk = Storage::disk('local');
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $modelClass = $this->argument('modelName');
        $type = $this->option('type');
        $force = $this->option('force');

        $batchEmbeddingService = app(BatchEmbeddingService::class, [
            'embeddableModelName' => $modelClass,
            'type' => $type,
        ]);

        $files = $this->disk->files($batchEmbeddingService->uploadFilesDir);

        if (count($files) > 0 && ! $force) {
            if (! $this->confirm('There are unuploaded files; would you like to overwrite?')) {
                return;
            }
            $this->info('Overwriting Files');
        }

        $batchEmbeddingService->generateJsonLFile(8000);
        $this->info('File generated successfully');

        $files = $this->disk->files($batchEmbeddingService->uploadFilesDir);

        if (count($files) < 1) {
            $this->info('No Files generated to upload. This user may already have been initialized.');
            return;
        }

        foreach ($files as $file) {
            try {
                $batchEmbeddingService->uploadFileForBatchEmbedding($this->disk->path($file));
                $this->info('File uploaded and batch created successfully! We will process it soon.');
            } catch (Exception $e) {
                $this->error('Error while uploading file for batch embedding: ' . $e->getMessage());
            }
        }
    }
}
