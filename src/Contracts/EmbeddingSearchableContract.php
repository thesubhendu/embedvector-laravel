<?php

namespace Subhendu\EmbedVector\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Contract for models that can be searched and found using embeddings.
 *
 * Use this for models that can be discovered via embedding similarity (e.g., Jobs that can be recommended).
 * These models are typically the "target" in embedding-based matching.
 *
 * Note: This interface extends EmbeddableContract because searchable models must also be embeddable
 * to generate their own embeddings for storage and comparison.
 *
 * @method \Illuminate\Database\Eloquent\Builder query()
 */
interface EmbeddingSearchableContract extends EmbeddableContract
{
    /**
     * Query builder for fetching models that need embeddings generated.
     */
    public function queryForEmbedding(): Builder;

    /**
     * Query builder for fetching models that need to be synced.
     */
    public function queryForSyncing(): Builder;
}
