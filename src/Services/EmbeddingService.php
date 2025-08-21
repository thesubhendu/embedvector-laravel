<?php

namespace Subhendu\EmbedVector\Services;

use Pgvector\Laravel\Vector;
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

    public function generateEmbedding(string $text): Vector
    {
        $response = $this->openai->embeddings()->create([
            'model' => $this->embeddingModel,
            'input' => $text,
        ]);

        $embedding = $response->embeddings[0]->embedding ?? null;

        if (! $embedding) {
            throw new \Exception('No embedding found in response');
        }

        return new Vector($embedding);
    }
}
