<?php

namespace Subhendu\EmbedVector\Traits;

use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;
use Subhendu\EmbedVector\Contracts\EmbeddingSearchableContract;
use Subhendu\EmbedVector\Models\Embedding;
use Subhendu\EmbedVector\Services\EmbeddingService;

/**
 * Trait for models that can be converted to text embeddings.
 * 
 * Use this trait with EmbeddableContract for models that generate embeddings
 * (e.g., Customer profiles for personalization).
 */
trait EmbeddableTrait
{
    public function getCustomId(): string
    {
        return (string) $this->getKey();
    }

    public function matchingResults(string $targetModelClass, int $topK = 5): Collection
    {
        $targetModel = app($targetModelClass);

        if (! $targetModel instanceof EmbeddingSearchableContract) {
            throw new \InvalidArgumentException(
                "Model class '{$targetModelClass}' cannot be searched. " .
                "Target model must implement EmbeddingSearchableContract to be searchable."
            );
        }

        $sourceEmbedding = $this->getEmbedding();

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

    /**
     * Get the embedding associated with this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function embedding()
    {
        return $this->morphOne(Embedding::class, 'model');
    }

    public function getEmbedding()
    {
        $sourceEmbedding = $this->embedding;

        if (! $sourceEmbedding || ($sourceEmbedding && $sourceEmbedding->embedding_sync_required)) {
            $sourceEmbeddingVector = app(EmbeddingService::class)->generateEmbedding($this->toEmbeddingText());
            $sourceEmbedding = $this->embedding()->updateOrCreate([], ['embedding' => $sourceEmbeddingVector, 'embedding_sync_required' => false]);
        }

        return $sourceEmbedding;
    }
}
