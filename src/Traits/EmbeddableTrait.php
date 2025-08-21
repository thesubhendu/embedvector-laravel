<?php

namespace Subhendu\EmbedVector\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Models\Embedding;
use Subhendu\EmbedVector\Services\EmbeddingService;

trait EmbeddableTrait
{
    public function getCustomId(): string
    {
        return (string) $this->getKey();
    }

    public function queryForEmbedding(): Builder
    {
        return $this->query();
    }

    public function queryForSyncing(): Builder
    {
        // Get IDs of models that need syncing from the embeddings table
        $modelsNeedingSync = Embedding::query()
            ->where('model_type', get_class($this))
            ->where('embedding_sync_required', true)
            ->pluck('model_id');

        // Return query for these models
        return $this->query()->whereIn($this->getKeyName(), $modelsNeedingSync);
    }

    public function matchingResults(string $targetModelClass, int $topK = 5): Collection
    {
        $targetModel = app($targetModelClass);

        if (! $targetModel instanceof EmbeddableContract) {
            throw new \InvalidArgumentException('Target model must implement EmbeddableContract');
        }

        // Retrieve current model's embedding
        $sourceEmbeddingQuery = Embedding::query()
            ->where('model_id', $this->getKey())
            ->where('model_type', get_class($this));


        $sourceEmbedding = $sourceEmbeddingQuery->first();


        if (! $sourceEmbedding || ($sourceEmbedding && $sourceEmbedding->embedding_sync_required)) {
            $sourceEmbeddingVector = app(EmbeddingService::class)->generateEmbedding($this->toEmbeddingText());
            $sourceEmbedding = $sourceEmbeddingQuery->updateOrCreate(['model_id' => $this->getKey(), 'model_type' => get_class($this)], ['embedding' => $sourceEmbeddingVector, 'embedding_sync_required' => false]);
        }

        // Determine distance metric (default: cosine) and corresponding operator
        $distanceMetric = strtolower((string) config('embedvector.distance_metric', 'cosine')) === 'l2'
            ? Distance::L2
            : Distance::Cosine;

        $operator = $distanceMetric === Distance::L2 ? '<->' : '<=>';

        // Get embedding scores from PostgreSQL
        $embeddingScores = $this->getEmbeddingScores($targetModelClass, $sourceEmbedding->embedding, $operator, $topK);

        // Get target models from their respective database
        $modelIds = $embeddingScores->pluck('model_id')->toArray();
        $targetModels = $targetModel->newQuery()
            ->whereIn($targetModel->getKeyName(), $modelIds)
            ->get()
            ->keyBy($targetModel->getKeyName());

        // Combine the results using Eloquent collection
        return $embeddingScores->map(function ($score) use ($targetModels) {
            $model = $targetModels->get($score['model_id']);
            if ($model) {
                // Add distance and match_percent as dynamic properties
                $model->distance = $score['distance'];
                $model->match_percent = $score['match_percent'];

                return $model;
            }

            return null;
        })->filter()->sortByDesc('match_percent')->values();
    }

    protected function getEmbeddingScores(string $targetModelClass, $sourceEmbedding, string $operator, int $topK): Collection
    {
        // Build scoring query using PostgreSQL connection
        $scores = Embedding::query()
            ->select('model_id')
            ->selectRaw("(embedding $operator ?) as distance", [$sourceEmbedding])
            ->selectRaw("LEAST(100, GREATEST(0, (1 - ((embedding $operator ?) / 2)) * 100)) as match_percent", [$sourceEmbedding])
            ->where('model_type', '=', $targetModelClass);

        if ($targetModelClass === get_class($this)) {
            $scores->where('model_id', '!=', $this->getKey());
        }

        return $scores->orderBy('distance', 'asc')->take($topK)->get();
    }
}
