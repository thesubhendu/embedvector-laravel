<?php

namespace Subhendu\Recommender;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Subhendu\Recommender\Commands\BatchEmbeddingCommand;
use Subhendu\Recommender\Commands\ProcessCompletedEmbeddingsCommand;

class RecommenderServiceProvider extends PackageServiceProvider
{
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
