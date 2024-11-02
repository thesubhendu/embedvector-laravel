<?php

namespace Subhendu\Recommender\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Pgvector\Vector;

/**
 * @method \Illuminate\Database\Eloquent\Builder query()
 */
interface EmbeddableContract
{
    /**
     * Converts the model instance to a text representation for embedding generation.
     */
    public function toEmbeddingText(): string;

    /**
     * Configures where to store the embedding vector .
     */
    public function getEmbeddingColumnName(): string;

    /**
     * Generates the embedding and stores it in the configured vector storage.
     */
    public function refreshEmbedding(): void;

    public function itemsToEmbed(): Builder;
}
