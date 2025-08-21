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

        // Determine distance metric (default: cosine) and corresponding operator
        $distanceMetric = strtolower((string) config('embedvector.distance_metric', 'cosine')) === 'l2'
            ? Distance::L2
            : Distance::Cosine;

        $operator = $distanceMetric === Distance::L2 ? '<->' : '<=>';

        // Build scoring subquery with distance and match_percent
        $scores = Embedding::query()
            ->select('model_id')
            ->selectRaw("(embedding $operator ?) as distance", [$sourceEmbedding])
            ->selectRaw("LEAST(100, GREATEST(0, (1 - ((embedding $operator ?) / 2)) * 100)) as match_percent", [$sourceEmbedding])
            ->where('model_type', '=', $targetModelClass);

        if ($targetModelClass === get_class($this)) {
            $scores->where('model_id', '!=', $this->getKey());
        }

        $scores->orderBy('distance', 'asc')->take($topK);

        // Join scored ids with target model to include computed columns
        $targetTable = $targetModel->getTable();
        $qualifiedKey = $targetModel->getQualifiedKeyName();

        return $targetModel->newQuery()
            ->select($targetTable.'.*')
            ->addSelect('scores.distance', 'scores.match_percent')
            ->joinSub($scores->toBase(), 'scores', function ($join) use ($qualifiedKey) {
                $join->on($qualifiedKey, '=', 'scores.model_id');
            })
            ->orderByDesc('scores.match_percent')
            ->get();
    }
}
