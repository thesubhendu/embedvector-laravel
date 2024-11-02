<?php

namespace Subhendu\Recommender\Services;

use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;
use Subhendu\Recommender\Contracts\EmbeddableContract;

class VectorDBService
{
    public function __construct() {}

    /**
     * @return Collection<int, EmbeddableContract>
     */
    public function querySimilarItems(
        EmbeddableContract $sourceModel,
        string $targetModelClass,
        int $topK = 5
    ): Collection {
        $targetModel = app($targetModelClass);

        if (! $targetModel instanceof EmbeddableContract) {
            throw new \InvalidArgumentException('Target model must implement EmbeddableContract');
        }

        return $targetModel->query()
            ->nearestNeighbors(
                $targetModel->getEmbeddingColumnName(),
                $sourceModel->{$sourceModel->getEmbeddingColumnName()},
                Distance::L2
            )
            ->take($topK)
            ->get();

    }
}
