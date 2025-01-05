<?php

namespace Subhendu\Recommender\Services;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;
use Subhendu\Recommender\Contracts\EmbeddableContract;
use Subhendu\Recommender\Models\EmbeddingBatch;

readonly class ProcessCompletedBatchService
{
    private Filesystem $disk;

    public function __construct(
    ) {
        $this->disk = Storage::disk('local');
    }

    public function process(EmbeddingBatch $batch): void
    {
        $embeddableModel = app($batch->embeddable_model);
        $handle = fopen($this->disk->path($batch->saved_file_path), 'r');

        if ($handle) {
            $batchSize = 500;
            $embeddingsBatch = [];

            while (($line = fgets($handle)) !== false) {
                $data = json_decode($line, true);
                $embeddingArray = $data['response']['body']['data'][0]['embedding'] ?? null;

                if (! $embeddingArray) {
                    continue;
                }
                $embeddingVector = new Vector($embeddingArray); // todo check if this is necessary

                if ($data) {
                    $embeddingsBatch[] = [
                        'model_id' => $data['custom_id'], // todo see if this can be customized too, or recruiter_id is necessary like for bude case
                        'embedding' => $embeddingVector->__toString(), // Store embedding as JSONB or text
                        'model_type' => $batch->embeddable_model,
                    ];

                    if (count($embeddingsBatch) >= $batchSize) {
                        $this->insertBatchIntoDatabase($embeddableModel, $embeddingsBatch);
                        $embeddingsBatch = []; // Clear the batch after insertion
                    }
                }
            }

            // Insert any remaining embeddings after the loop finishes
            if (count($embeddingsBatch) > 0) {
                $this->insertBatchIntoDatabase($embeddableModel, $embeddingsBatch);
            }

            fclose($handle);

            $batch->status = 'archived'; // done processing now save for reference only
            $batch->save();
            $this->disk->delete($batch->saved_file_path);
        } else {
            throw new Exception('Unable to open the file.');
        }
    }

    private function insertBatchIntoDatabase(EmbeddableContract $embeddableModel, array $embeddingsBatch): void
    {
        DB::table('embeddings')->upsert($embeddingsBatch, ['model_id','model_type'], ['embedding']);
    }
}
