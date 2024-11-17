<?php

namespace Subhendu\Recommender\Traits;

use Illuminate\Database\Eloquent\Builder;
use Subhendu\Recommender\Models\SyncEmbeddingQueue;
use Subhendu\Recommender\Services\EmbeddingService;
use Pgvector\Laravel\Vector;

trait EmbeddableTrait
{

    public function getEmbedding(): Vector
    {
       return $this->{$this->getEmbeddingColumnName()};
    }

    /**
     * Generates the embedding and stores it in the configured vector storage.
     *
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

    public function itemsToSync(): Builder
    {
        $modelIds = SyncEmbeddingQueue::where('model_type', get_class($this))->pluck('model_id');


        return $this->query()->whereIn($this->getKeyName(), $modelIds);
    }

}
