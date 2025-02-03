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

        $embeddingsValue = DB::table('embeddings')
            ->where('model_id', $this->getKey())
            ->where('model_type', get_class($this))
            ->value('embedding');

        $matchingResultIds = Embedding::query()
            ->nearestNeighbors(
                'embedding',
                $embeddingsValue,
                Distance::L2
            )
            ->where('model_type', '=', $targetModelClass)
            ->take($topK)
            ->pluck('model_id');

        return $targetModel->whereIn($targetModel->getKeyName(), $matchingResultIds)->get();
    }
}
