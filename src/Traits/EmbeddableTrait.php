<?php

namespace Subhendu\EmbedVector\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Distance;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Models\Embedding;

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
        $tableName = $this->getTable();
        $customersWithSyncRequiredEmbeddings = $this->query()
            ->join('embeddings', function ($join) use ($tableName) {
                $join->on($tableName.'.id', '=', 'embeddings.model_id')
                    ->where('embeddings.model_type', '=', get_class($this));
            })
            ->where('embeddings.embedding_sync_required', true)
            ->select($tableName.'.*');

        return $customersWithSyncRequiredEmbeddings;
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
            return collect();
        }

        // Determine distance metric (default: cosine)
        $distanceMetric = strtolower((string) config('embedvector.distance_metric', 'cosine')) === 'l2'
            ? Distance::L2
            : Distance::COSINE;

        $query = Embedding::query()
            ->nearestNeighbors('embedding', $sourceEmbedding, $distanceMetric)
            ->where('model_type', '=', $targetModelClass)
            ->take($topK);

        // Exclude self when matching within the same model class
        if ($targetModelClass === get_class($this)) {
            $query->where('model_id', '!=', $this->getKey());
        }

        $matchingResultIds = $query->pluck('model_id');

        return $targetModel->whereIn($targetModel->getKeyName(), $matchingResultIds)->get();
    }
}
