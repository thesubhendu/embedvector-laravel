<?php

namespace Subhendu\EmbedVector\Traits;

use Pgvector\Laravel\Distance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Subhendu\EmbedVector\Models\Embedding;
use Subhendu\EmbedVector\Services\EmbeddingService;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;

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
        $sourceEmbedding = Embedding::query()
            ->where('model_id', $this->getKey())
            ->where('model_type', get_class($this))
            ->first()?->embedding;

        if (! $sourceEmbedding) {
            $sourceEmbedding = app(EmbeddingService::class)->generateEmbedding($this->toEmbeddingText());
        }

        // Determine distance metric (default: cosine) and corresponding operator
        $distanceMetric = strtolower((string) config('embedvector.distance_metric', 'cosine')) === 'l2'
            ? Distance::L2
            : Distance::Cosine;

        $operator = $distanceMetric === Distance::L2 ? '<->' : '<=>';

        // Get embedding scores from PostgreSQL
        $embeddingScores = $this->getEmbeddingScores($targetModelClass, $sourceEmbedding, $operator, $topK);

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
