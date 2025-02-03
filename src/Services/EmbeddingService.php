<?php

namespace Subhendu\EmbedVector\Services;

use OpenAI\Contracts\ClientContract;

class EmbeddingService
{
    private ClientContract $openai;

    public string $embeddingModel = 'text-embedding-3-small';

    public function __construct(ClientContract $openaiClient)
    {
        $this->openai = $openaiClient;
    }

    public function getClient()
    {
        return $this->openai;
    }
}
