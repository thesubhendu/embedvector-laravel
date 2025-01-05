<?php

namespace Subhendu\Recommender\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Subhendu\Recommender\Models\EmbeddingBatch;
use Subhendu\Recommender\Services\EmbeddingService;
use Subhendu\Recommender\Services\ProcessCompletedBatchService;

class ProcessCompletedEmbeddingsCommand extends Command
{
    protected $signature = 'process-completed-batch';

    public const outputFileDirectory = 'embeddings/output';

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
        $batchesToCheck = EmbeddingBatch::whereIn('status', ['validating', 'finalizing', 'in_progress'])->get();

        $completedButUnprocessedBatches = EmbeddingBatch::where('status', 'completed')->get();

        foreach ($completedButUnprocessedBatches as $completedBatch) {
            $this->info('Retrying processing of already completed batches'.$completedBatch->id);
            $completedBatchService->process($completedBatch);
        }

        foreach ($batchesToCheck as $batch) {
            $response = $this->embeddingService->getClient()->batches()->retrieve(id: $batch->batch_id);

            if (! $response->status) {
                $this->info('no status found skipping the batch '.$batch->id);
                continue;
            }

            $this->info('Current Status: ' . $response->status);

            if ($response->status == 'completed') {

                $this->info('Completed Batch Found -> '.$batch->id);
                $this->info('Downloading Result File');
                $filePath = $this->downloadAndSaveFile($batch, $response->outputFileId);

                $this->info('File Downloaded and saved at ' . $filePath);

                $this->info('Started Processing batch id: '. $batch->id);

                $completedBatchService->process($batch);

            } else {
                // update latest status of batch
                $batch->status = $response->status;
                $batch->save();
                $this->info('batch not completed, updating its latest status '.$batch->id);
            }
        }

    }

    private function downloadAndSaveFile(EmbeddingBatch $batch, string $outputFileId): string
    {
        $filePath = self::outputFileDirectory.'/output_embeddings_'.$batch->id.'.jsonl';

        Storage::disk('local')->put($filePath, $this->embeddingService->getClient()->files()->download($outputFileId));

        $batch->saved_file_path = $filePath;
        $batch->output_file_id = $outputFileId;
        $batch->status = 'completed';
        $batch->save();

        return $filePath;
    }
}
