<?php

namespace Subhendu\Recommender\Services;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;
use Subhendu\Recommender\Models\EmbeddingBatch;

readonly class ProcessCompletedBatchService
{
    private Filesystem $disk;

    public function __construct(
    ) {
        $this->disk = Storage::disk('local');
        //        $this->disk->deleteDirectory(self::outputFileDirectory, true);
    }

    public function process(EmbeddingBatch $batch): void
    {
        $embeddingStorageModel = app($batch->embeddable_model);
        $embeddingStorageTableName = $embeddingStorageModel->getTable();
        $embeddingColumnName = $embeddingStorageModel->getEmbeddingColumnName();
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
                        $embeddingStorageModel->keyName() => $data['custom_id'],
                        $embeddingColumnName => $embeddingVector->__toString(), // Store embedding as JSONB or text
                    ];

                    if (count($embeddingsBatch) >= $batchSize) {
                        $this->insertBatchIntoDatabase($embeddingStorageTableName, $embeddingsBatch);
                        $embeddingsBatch = []; // Clear the batch after insertion
                    }
                }
            }

            // Insert any remaining embeddings after the loop finishes
            if (count($embeddingsBatch) > 0) {
                $this->insertBatchIntoDatabase($embeddingStorageTableName, $embeddingsBatch);
            }

            fclose($handle);

            // delete file
            //            $this->disk->delete($batch->saved_file_path);
            $batch->status = 'archived'; // done processing now save for reference only
            $batch->save();
        } else {
            throw new Exception('Unable to open the file.');
        }
    }

    private function insertBatchIntoDatabase(string $tableName, array $embeddingsBatch): void
    {
        // todo upsert it, for that make sure there is unique constraint on model_id column
        DB::connection('pgsql')->table($tableName)->upsert($embeddingsBatch, ['id'], ['embedding']);
    }
}
