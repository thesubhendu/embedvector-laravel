<?php

namespace Subhendu\Recommender\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Orchestra;
use Subhendu\Recommender\RecommenderServiceProvider;

use function Orchestra\Testbench\package_path;
use function Orchestra\Testbench\workbench_path;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Subhendu\\Recommender\\Tests\\Fixtures\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            RecommenderServiceProvider::class,
        ];
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(
                 package_path('/database/migrations/'),
        );
        $this->loadMigrationsFrom(
                 package_path('/tests/Fixtures/Migrations/'),
        );
    }

    public function defineEnvironment($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing',
            [
                'driver' => 'pgsql',
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'testonlyman',
                'username' => 'devsub',
                'password' => 'ram',
            ]
        );
    }
}
