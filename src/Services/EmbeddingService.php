<?php

namespace Subhendu\EmbedVector\Services;

use OpenAI\Contracts\ClientContract;

class EmbeddingService
{
    private ClientContract $openai;

    public function __construct(ClientContract $openaiClient)
    {
        $this->openai = $openaiClient;
    }

    public function getClient()
    {
        return $this->openai;
    }

    public function getEmbeddingModel()
    {
        return config('embedvector.embedding_model');
    }
}
