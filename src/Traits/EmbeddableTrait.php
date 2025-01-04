<?php

namespace Subhendu\Recommender\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;
use Subhendu\Recommender\Contracts\EmbeddableContract;

trait EmbeddableTrait
{
    public function getEmbeddingColumnName(): string
    {
        return 'embedding';
    }

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
        return $this->query()->where('embedding_sync_required', true);
    }

    public function matchingResults(string $targetModelClass, int $topK = 5): Collection
    {
        $targetModel = app($targetModelClass);

        if (! $targetModel instanceof EmbeddableContract) {
            throw new \InvalidArgumentException('Target model must implement EmbeddableContract');
        }

        return $targetModel->query()
            ->nearestNeighbors(
                $targetModel->getEmbeddingColumnName(),
                $this->{$this->getEmbeddingColumnName()},
                Distance::L2
            )
            ->take($topK)
            ->get();
    }
}
