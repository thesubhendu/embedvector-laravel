<?php

namespace Subhendu\EmbedVector\Traits;

use Illuminate\Database\Eloquent\Builder;
use Subhendu\EmbedVector\Models\Embedding;

/**
 * Trait for models that can be searched and found using embeddings.
 * 
 * Use this trait with EmbeddingSearchableContract for models that can be both
 * embedded AND searched (e.g., Jobs that can be recommended).
 * 
 * This trait automatically includes EmbeddableTrait functionality.
 */
trait EmbeddingSearchableTrait
{
    use EmbeddableTrait;

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


}
