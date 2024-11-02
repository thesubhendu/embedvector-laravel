<?php
namespace Subhendu\Recommender\Services;

use Illuminate\Support\Collection;
use Subhendu\Recommender\Contracts\EmbeddableContract;
use Pgvector\Laravel\Distance;

class VectorDBService
{

    public function __construct()
    {
    }

    /**
     * @param EmbeddableContract $sourceModel
     * @param string $targetModelClass
     * @param int $topK
     * @return Collection<int, EmbeddableContract>
     */
    public function querySimilarItems(
        EmbeddableContract $sourceModel,
        string $targetModelClass,
        int $topK = 5
    ): Collection
    {
        $targetModel = app($targetModelClass);

        if (!$targetModel instanceof EmbeddableContract) {
            throw new \InvalidArgumentException("Target model must implement EmbeddableContract");
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
