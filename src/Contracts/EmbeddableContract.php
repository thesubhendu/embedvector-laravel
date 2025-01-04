<?php

namespace Subhendu\Recommender\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @method \Illuminate\Database\Eloquent\Builder query()
 */
interface EmbeddableContract
{
    /**
     * Converts the model instance to a text representation for embedding generation.
     */
    public function toEmbeddingText(): string;

    public function getEmbeddingColumnName(): string;

    public function queryForEmbedding(): Builder;

    public function queryForSyncing(): Builder;

    /**
     * Gives matching $targetModelClass for the Model
     *
     * @param  string  $targetModelClass  Example. To search for Jobs for the customer Jobs are targetClass
     * @param  int  $topK  number of top results
     * @return Collection<int, EmbeddableContract>
     */
    public function matchingResults(string $targetModelClass, int $topK = 5): Collection;

    /**
     * custom id that is added to the embedding file for upload, this id is used to identify the model while processing the result file from openai
     */
    public function getCustomId(): string;
}
