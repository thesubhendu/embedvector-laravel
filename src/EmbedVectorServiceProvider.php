<?php

namespace Subhendu\EmbedVector;

use OpenAI;
use OpenAI\Client;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Subhendu\EmbedVector\Commands\BatchEmbeddingCommand;
use Subhendu\EmbedVector\Commands\ProcessCompletedEmbeddingsCommand;
use Subhendu\EmbedVector\Exceptions\EmbeddingException;

class EmbedVectorServiceProvider extends PackageServiceProvider
{
    public function boot()
    {

        $this->app->singleton(Client::class, function ($app) {
            $apiKey = config('embedvector.openai_api_key');

            // Allow empty API key in testing environment
            if (empty($apiKey) && ! $app->environment('testing')) {
                throw EmbeddingException::invalidApiKey();
            }

            return OpenAI::client($apiKey ?: 'test-key');
        });

        $this->app->bind(OpenAI\Contracts\ClientContract::class, Client::class);

        return parent::boot();
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('embedvector')
            ->hasConfigFile()
            ->runsMigrations() // Auto-run migrations from package - NO PUBLISHING NEEDED
//            ->hasViews()
            ->hasMigration('2022_08_03_000000_create_vector_extension')
            ->hasMigration('2024_10_24_095247_create_embedding_batches_table')
            ->hasMigration('2025_01_05_090159_create_embeddings_table')
            ->hasMigration('2025_01_15_000001_add_vector_indexes')
            ->hasCommands([BatchEmbeddingCommand::class, ProcessCompletedEmbeddingsCommand::class]);
    }
}
