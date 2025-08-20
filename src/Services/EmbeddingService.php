<?php

namespace Subhendu\EmbedVector\Services;

use OpenAI\Contracts\ClientContract;

class EmbeddingService
{
    private ClientContract $openai;

    public string $embeddingModel;

    public function __construct(ClientContract $openaiClient)
    {
        $this->openai = $openaiClient;
        $this->embeddingModel = (string) config('embedvector.embedding_model', 'text-embedding-3-small');
    }

    public function getClient(): ClientContract
    {
        return $this->openai;
    }
}
