<?php

namespace Subhendu\Recommender;

use OpenAI;
use OpenAI\Client;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Subhendu\Recommender\Commands\BatchEmbeddingCommand;
use Subhendu\Recommender\Commands\ProcessCompletedEmbeddingsCommand;

class RecommenderServiceProvider extends PackageServiceProvider
{
    public function boot()
    {

        $this->app->singleton(Client::class, function ($app) {
            return OpenAI::client(config('recommender.openai_api_key'));
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
            ->name('recommender')
            ->hasConfigFile()
//            ->hasViews()
            ->hasMigration('2024_10_24_095247_create_embedding_batches_table')
            ->hasMigration('2025_01_05_090159_create_embeddings_table')
            ->hasCommands([BatchEmbeddingCommand::class, ProcessCompletedEmbeddingsCommand::class]);
    }
}
