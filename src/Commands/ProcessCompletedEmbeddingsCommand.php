<?php

namespace Subhendu\EmbedVector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Subhendu\EmbedVector\Models\EmbeddingBatch;
use Subhendu\EmbedVector\Services\EmbeddingService;
use Subhendu\EmbedVector\Services\ProcessCompletedBatchService;

class ProcessCompletedEmbeddingsCommand extends Command
{
    protected $signature = 'embedding:proc {--batch-id=} {--all}';

    protected $description = 'Process Completed Batches';

    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(ProcessCompletedBatchService $completedBatchService): void
    {
        $batchId = $this->option('batch-id');
        $processAll = $this->option('all');

        if ($batchId) {
            // Process specific batch
            $batch = EmbeddingBatch::find($batchId);
            if (!$batch) {
                $this->error("Batch with ID {$batchId} not found.");
                return;
            }
            
            $this->processSingleBatch($batch, $completedBatchService);
            return;
        }

        if ($processAll) {
            // Process all completed batches
            $completedButUnprocessedBatches = EmbeddingBatch::where('status', 'completed')->get();
            
            foreach ($completedButUnprocessedBatches as $completedBatch) {
                $this->info('Processing completed batch: ' . $completedBatch->id);
                $completedBatchService->process($completedBatch);
            }
        }

        // Check and process batches that might be ready
        $batchesToCheck = EmbeddingBatch::whereIn('status', ['validating', 'finalizing', 'in_progress'])->get();

        foreach ($batchesToCheck as $batch) {
            $response = $this->embeddingService->getClient()->batches()->retrieve(id: $batch->batch_id);

            if (! $response->status) {
                $this->info('no status found skipping the batch '.$batch->id);

                continue;
            }

            $this->info('Current Status: '.$response->status);

            if ($response->status == 'completed') {

                $this->info('Completed Batch Found -> '.$batch->id);
                $this->info('Downloading Result File');
                $filePath = $this->downloadAndSaveFile($batch, $response->outputFileId);

                $this->info('File Downloaded and saved at '.$filePath);

                $this->info('Started Processing batch id: '.$batch->id);

                $completedBatchService->process($batch);

            } else {
                // update latest status of batch
                $batch->status = $response->status;
                $batch->save();
                $this->info('batch not completed, updating its latest status '.$batch->id);
            }
        }
    }

    private function processSingleBatch(EmbeddingBatch $batch, ProcessCompletedBatchService $completedBatchService): void
    {
        $this->info('Processing single batch: ' . $batch->id);
        
        if ($batch->status === 'completed') {
            $this->info('Batch is already completed, processing...');
            $completedBatchService->process($batch);
        } else {
            $this->info('Batch status: ' . $batch->status);
            $this->info('This batch is not ready for processing yet.');
        }
    }

    private function downloadAndSaveFile(EmbeddingBatch $batch, string $outputFileId): string
    {
        $filePath = config('embedvector.directories.output').'/output_embeddings_'.$batch->id.'.jsonl';

        Storage::disk('local')->put($filePath, $this->embeddingService->getClient()->files()->download($outputFileId));

        $batch->saved_file_path = $filePath;
        $batch->output_file_id = $outputFileId;
        $batch->status = 'completed';
        $batch->save();

        return $filePath;
    }
}
