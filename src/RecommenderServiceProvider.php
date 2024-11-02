<?php

namespace Subhendu\Recommender;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Subhendu\Recommender\Commands\BatchEmbeddingCommand;
use Subhendu\Recommender\Commands\ProcessCompletedEmbeddingsCommand;
use Subhendu\Recommender\Commands\RecommenderCommand;

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
            ->hasViews()
            ->hasMigration('create_recommender_table')
            ->hasCommands([BatchEmbeddingCommand::class, ProcessCompletedEmbeddingsCommand::class]);
    }
}
