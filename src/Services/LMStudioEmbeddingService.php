<?php

namespace Subhendu\EmbedVector\Services;

use Exception;
use Illuminate\Support\Collection;
use OpenAI\Contracts\ClientContract;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Contracts\EmbeddingServiceContract;
use Subhendu\EmbedVector\Models\Embedding;

class LMStudioEmbeddingService implements EmbeddingServiceContract
{
    private EmbeddableContract $embeddableModel;

    public function __construct(
        private readonly ClientContract $client,
    ) {}

    public function process(string $modelClass, string $type = 'sync'): array
    {
        $this->embeddableModel = app($modelClass);
        $results = ['success' => true, 'messages' => []];

        try {
            $chunkSize = config('embedvector.chunk_size', 500);
            $query = $type === 'init'
                ? $this->embeddableModel->queryForEmbedding()
                : $this->embeddableModel->queryForSyncing();

            $query->chunkById($chunkSize, function ($models) use (&$results) {
                $accumulatedInputs = [];
                foreach ($models as $model) {
                    try {
                        $accumulatedInputs[] = $model->toEmbeddingText();
                        // Save embedding to database
                        // Implementation depends on your storage approach
                        $results['messages'][] = "Processed embedding for ID: {$model->getCustomId()}";
                    } catch (Exception $e) {
                        $results['messages'][] = "Error processing ID {$model->getCustomId()}: {$e->getMessage()}";
                    }
                }

                if (count($accumulatedInputs) > 0) {
                    $embeddings = $this->createEmbedding($accumulatedInputs);
                    $this->saveEmbeddings($embeddings, $models);
                }
            });

        } catch (Exception $e) {
            $results['success'] = false;
            $results['messages'][] = 'Error during processing: ' . $e->getMessage();
        }

        return $results;
    }

    public function createEmbedding(string|array $input): array|string
    {
        $response = $this->client->embeddings()->create([
            'model' => config('embedvector.embedding_model'),
            'input' => $input,
        ]);

        if(is_array($input)) {
            return $response->embeddings;
        }

        return $response->embeddings[0]->embedding;
    }

    public function saveEmbeddings(array $embeddings, Collection $models): void
    {

        foreach ($embeddings as $embedding) {
            $model = $models[$embedding->index];
            Embedding::create([
                'model_id' => $model->getCustomId(),
                'embedding' => $embedding->embedding,
                'model_type' => get_class($model),
            ]);
        }
    }
}
