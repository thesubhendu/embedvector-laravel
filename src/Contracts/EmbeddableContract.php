<?php

namespace Subhendu\EmbedVector\Contracts;

use Illuminate\Support\Collection;

/**
 * Contract for models that can be converted to text embeddings.
 *
 * Use this for models that generate embeddings (e.g., Customer profiles for personalization).
 * These models are typically the "source" in embedding-based matching.
 */
interface EmbeddableContract
{
    /**
     * Converts the model instance to a text representation for embedding generation.
     */
    public function toEmbeddingText(): string;

    /**
     * Custom id that is added to the embedding file for upload.
     * This id is used to identify the model while processing the result file from OpenAI.
     */
    public function getCustomId(): string;

    /**
     * Find matching models of the specified target class using embedding similarity.
     *
     * @param  string  $targetModelClass  The model class to search for (e.g., JobVerified::class)
     * @param  int  $topK  Number of top results to return
     * @param  \Closure|null  $queryFilter  Optional closure to apply filters to the target query
     * @return Collection<int, mixed>
     */
    public function matchingResults(string $targetModelClass, int $topK = 5, ?\Closure $queryFilter = null): Collection;
}
