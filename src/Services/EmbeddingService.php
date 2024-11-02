<?php

namespace Subhendu\Recommender\Services;

use OpenAI;

class EmbeddingService
{
    private OpenAI\Client $openai;

    public string $embeddingModel = 'text-embedding-3-small';

    public function __construct()
    {

        $this->openai = OpenAI::client(config('recommender.openai_api_key'));
    }

    public function getClient()
    {
        return $this->openai;
    }

    /**
     * @return array<int,float>
     */
    public function generateEmbedding(string $text): array
    {
        $response = $this->openai->embeddings()->create([
            'model' => $this->embeddingModel,
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }
}
