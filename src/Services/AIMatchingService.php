<?php

namespace Subhendu\Recommender\Services;

use Illuminate\Support\Collection;
use Subhendu\Recommender\Contracts\EmbeddableContract;

readonly class AIMatchingService
{
    public function __construct(
        private VectorDBService $vectorDBService,
    )
    {
    }

    /**
     * @param EmbeddableContract $source
     * @param string $targetModelClass
     * @param int $topK
     * @return Collection<int, EmbeddableContract>
     */
    public function getMatchingResults(EmbeddableContract $source, string $targetModelClass, int $topK = 5): Collection
    {
        return $this->vectorDBService->querySimilarItems($source, $targetModelClass, $topK);
    }

}
