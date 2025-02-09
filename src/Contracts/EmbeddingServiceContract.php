<?php

namespace Subhendu\EmbedVector\Contracts;

interface EmbeddingServiceContract
{
    /**
     * Process the embedding batch
     *
     * @param string $modelClass The model class to process embeddings for
     * @param string $type The type of processing (init/sync)
     * @param bool $force Whether to force processing even if files exist
     * @return array{success: bool, messages: string[]}
     */
    public function process(string $modelClass, string $type = 'sync'): array;

}
