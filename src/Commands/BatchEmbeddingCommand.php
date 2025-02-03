<?php

namespace Subhendu\EmbedVector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Subhendu\EmbedVector\Services\BatchEmbeddingService;

class BatchEmbeddingCommand extends Command
{
    protected $signature = 'embedding:batch {modelName} {--type=sync} {--force}';

    protected $description = 'Generate a JSONL file for batch embedding of JobVerified models';

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
        $force = $this->option('force'); // overwrite

        $batchEmbeddingService = app(BatchEmbeddingService::class, [
            'embeddableModelName' => $modelClass,
            'type' => $type,
        ]);

        $files = $this->disk->files($batchEmbeddingService->uploadFilesDir);

        if (count($files) > 0 && ! $force) {
            $ans = $this->confirm('There are un uploaded files, would you like to overwrite?');

            if (! $ans) {
                return;
            }
            $this->info('Overwriting Files');
        }

        $batchEmbeddingService->generateJsonLFile(8000);

        $this->info('file generated success');
        $files = $this->disk->files($batchEmbeddingService->uploadFilesDir);

        try {
            if (count($files) < 1) {
                $this->info('No Files generated to upload, This user may have already been initialized');
            } else {
                $this->info('Files found: '.json_encode($files));
            }

            foreach ($files as $file) {
                $batchEmbeddingService->uploadFileForBatchEmbedding($this->disk->path($file));

                $this->info('File uploaded and batch created successfully! We will process it soon.');
            }
        } catch (\Exception $e) {
            $this->error('Error occurred while uploading file for batch embedding: '.$e->getMessage());
        }

    }
}
