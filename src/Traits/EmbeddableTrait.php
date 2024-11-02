<?php

namespace Subhendu\Recommender\Traits;

use Illuminate\Database\Eloquent\Builder;
use Pgvector\Laravel\Vector;
use Subhendu\Recommender\Services\EmbeddingService;

trait EmbeddableTrait
{
    public function getEmbedding(): Vector
    {
        return $this->{$this->getEmbeddingColumnName()};
    }

    /**
     * Generates the embedding and stores it in the configured vector storage.
     */
    public function refreshEmbedding(): void
    {
        $embedding = app(EmbeddingService::class)->generateEmbedding($this->toEmbeddingText());

        $this->{$this->getEmbeddingColumnName()} = new Vector($embedding);
        $this->save();
    }

    public function getEmbeddingColumnName(): string
    {
        return 'embedding';
    }

    public function itemsToEmbed(): Builder
    {
        return $this->query();
    }
}
