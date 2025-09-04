<?php

namespace Subhendu\EmbedVector\Exceptions;

use Exception;

class EmbeddingException extends Exception
{
    public static function noEmbeddingFound(): self
    {
        return new self('No embedding found in OpenAI response');
    }

    public static function invalidApiKey(): self
    {
        return new self('OpenAI API key is not configured. Please set OPENAI_API_KEY environment variable.');
    }

    public static function fileOperationFailed(string $operation, string $path, string $reason = ''): self
    {
        $message = "File {$operation} failed for path: {$path}";
        if ($reason) {
            $message .= ". Reason: {$reason}";
        }

        return new self($message);
    }

    public static function batchNotFound(string $batchId): self
    {
        return new self("Batch with ID {$batchId} not found.");
    }

    public static function invalidModel(string $modelClass, string $contract): self
    {
        return new self("Model class '{$modelClass}' must implement {$contract} interface.");
    }

    public static function embeddingGenerationFailed($message = ''): self
    {
        return new self('Embedding generation failed: ' . $message);
    }
}
