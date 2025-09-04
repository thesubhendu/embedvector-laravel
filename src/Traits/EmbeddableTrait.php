<?php

namespace Subhendu\EmbedVector\Traits;

use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;
use Subhendu\EmbedVector\Contracts\EmbeddingSearchableContract;
use Subhendu\EmbedVector\Exceptions\EmbeddingException;
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

    public function matchingResults(string $targetModelClass, int $topK = 5, ?\Closure $queryFilter = null): Collection
    {
        $targetModel = app($targetModelClass);

        if (! $targetModel instanceof EmbeddingSearchableContract) {
            throw EmbeddingException::invalidModel(
                $targetModelClass,
                EmbeddingSearchableContract::class
            );
        }

        $sourceEmbedding = $this->getOrCreateEmbedding();
       


        // Determine distance metric (default: cosine) and corresponding operator
        $distanceMetric = strtolower((string) config('embedvector.distance_metric', 'cosine')) === 'l2'
            ? Distance::L2
            : Distance::Cosine;

        $operator = $distanceMetric === Distance::L2 ? '<->' : '<=>';

        // Choose strategy based on configuration
        $strategy = config('embedvector.search_strategy', 'auto');

        switch ($strategy) {
            case 'optimized':
                return $this->getMatchingResultsOptimized($targetModel, $sourceEmbedding->embedding, $operator, $topK, $queryFilter);

            case 'cross_connection':
                return $this->getMatchingResultsCrossConnection($targetModel, $sourceEmbedding->embedding, $operator, $topK, $queryFilter);

            case 'auto':
            default:
                // Auto-detect if models are on the same database connection
                if ($this->isSameConnection($targetModel)) {
                    return $this->getMatchingResultsOptimized($targetModel, $sourceEmbedding->embedding, $operator, $topK, $queryFilter);
                }

                // Fallback to cross-connection approach for different databases
                return $this->getMatchingResultsCrossConnection($targetModel, $sourceEmbedding->embedding, $operator, $topK, $queryFilter);
        }
    }

    /**
     * Check if the target model is on the same database connection as embeddings.
     *
     * @param \Illuminate\Database\Eloquent\Model $targetModel
     * @return bool
     */
    protected function isSameConnection($targetModel): bool
    {
        $embeddingConnection = (new Embedding)->getConnectionName();
        $targetConnection = $targetModel->getConnectionName();

        return $embeddingConnection === $targetConnection;
    }

    /**
     * Optimized matching for same database connection using JOIN.
     *
     * @param \Illuminate\Database\Eloquent\Model $targetModel
     * @param mixed $sourceEmbedding
     * @param string $operator
     * @param int $topK
     * @param \Closure|null $queryFilter
     * @return Collection
     */
    protected function getMatchingResultsOptimized($targetModel, $sourceEmbedding, string $operator, int $topK, ?\Closure $queryFilter = null): Collection
    {
        $targetTable = $targetModel->getTable();
        $targetKeyName = $targetModel->getKeyName();
        $embeddingTable = (new Embedding)->getTable();

        $query = $targetModel->newQuery()
            ->join($embeddingTable, function ($join) use ($targetTable, $targetKeyName, $embeddingTable, $targetModel) {
                $join->on("{$embeddingTable}.model_id", '=', "{$targetTable}.{$targetKeyName}")
                    ->where("{$embeddingTable}.model_type", '=', get_class($targetModel));
            })
            ->selectRaw("{$targetTable}.*")
            ->selectRaw("({$embeddingTable}.embedding $operator ?) as distance", [$sourceEmbedding])
            ->selectRaw("LEAST(100, GREATEST(0, (1 - (({$embeddingTable}.embedding $operator ?) / 2)) * 100)) as match_percent", [$sourceEmbedding]);

        if ($queryFilter) {
            $queryFilter($query);
        }

        // Exclude self-matching if source and target are the same model type
        if (get_class($this) === get_class($targetModel)) {
            $query->where("{$targetTable}.{$targetKeyName}", '!=', $this->getKey());
        }

        return $query->orderBy('distance', 'asc')
            ->limit($topK)
            ->get()
            ->map(function ($model) {
                $model->distance = (float) ($model->getAttributes()['distance'] ?? 0);
                $model->match_percent = (float) ($model->getAttributes()['match_percent'] ?? 0);

                return $model;
            })
            ->sortByDesc('match_percent')
            ->values();
    }

    /**
     * Cross-connection matching for different databases (current approach).
     *
     * @param \Illuminate\Database\Eloquent\Model $targetModel
     * @param mixed $sourceEmbedding
     * @param string $operator
     * @param int $topK
     * @param \Closure|null $queryFilter
     * @return Collection
     */
    protected function getMatchingResultsCrossConnection($targetModel, $sourceEmbedding, string $operator, int $topK, ?\Closure $queryFilter = null): Collection
    {
        // If filter is provided, get valid model IDs first to ensure consistent behavior
        $validModelIds = null;
        if ($queryFilter) {
            $filteredQuery = $targetModel->newQuery();
            $queryFilter($filteredQuery);
            $validModelIds = $filteredQuery->pluck($targetModel->getKeyName())->toArray();

            // If no models match the filter, return empty collection
            if (empty($validModelIds)) {
                return collect();
            }
        }

        // Get embedding scores from PostgreSQL, optionally restricted to valid IDs
        $embeddingScores = $this->getEmbeddingScores(get_class($targetModel), $sourceEmbedding, $operator, $topK, $validModelIds);

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
                $model->distance = (float) $score['distance'];
                $model->match_percent = (float) $score['match_percent'];

                return $model;
            }

            return null;
        })->filter()->sortByDesc('match_percent')->values();
    }

    /**
     * Get embedding scores from the database.
     *
     * @param string $targetModelClass
     * @param mixed $sourceEmbedding
     * @param string $operator
     * @param int $topK
     * @param array|null $validModelIds
     * @return Collection
     */
    protected function getEmbeddingScores(string $targetModelClass, $sourceEmbedding, string $operator, int $topK, ?array $validModelIds = null): Collection
    {
        // Build scoring query using PostgreSQL connection
        $scores = Embedding::query()
            ->select('model_id')
            ->selectRaw("(embedding $operator ?) as distance", [$sourceEmbedding])
            ->selectRaw("LEAST(100, GREATEST(0, (1 - ((embedding $operator ?) / 2)) * 100)) as match_percent", [$sourceEmbedding])
            ->where('model_type', '=', $targetModelClass);

        // Restrict to valid model IDs if provided (for cross-connection filtering)
        if ($validModelIds !== null) {
            $scores->whereIn('model_id', $validModelIds);
        }

        if ($targetModelClass === get_class($this)) {
            $scores->where('model_id', '!=', $this->getKey());
        }

        return $scores->take($topK)->get();
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

    /**
     * Get or create embedding for this model.
     *
     * @return ?Embedding
     */
    public function getEmbedding(): ?Embedding
    {
        return Embedding::query()
            ->where('model_type', get_class($this))
            ->where('model_id', $this->getKey())
            ->first();

    }
  
    /**
     * Get or create embedding for this model.
     *
     * @return Embedding
     */
    public function getOrCreateEmbedding(): Embedding
    {
        $sourceEmbedding = Embedding::query()
            ->where('model_type', get_class($this))
            ->where('model_id', $this->getKey())
            ->first();

        if (!$sourceEmbedding || $sourceEmbedding->embedding_sync_required) {
            return $this->createFreshEmbedding();
        }

        return $sourceEmbedding;
    }

    public function queueForSyncing(): void
    {
        $embedding = $this->getEmbedding();
        
        if ($embedding) {
            $embedding->update(['embedding_sync_required' => true]);
        }
    }

    public function createFreshEmbedding(): Embedding
    {
        try {
            $sourceEmbeddingVector = app(EmbeddingService::class)
                ->generateEmbedding($this->toEmbeddingText());

            $sourceEmbedding = $this->embedding()->updateOrCreate(
                [
                    'model_type' => get_class($this),
                    'model_id' => $this->getKey(),
                ],
                ['embedding' => $sourceEmbeddingVector, 'embedding_sync_required' => false]
            );

            return $sourceEmbedding;
        } catch (\Exception $e) {
            throw EmbeddingException::embeddingGenerationFailed($e->getMessage());
        }
    }

    
}
