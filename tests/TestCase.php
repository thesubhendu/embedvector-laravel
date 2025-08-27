<?php

namespace Subhendu\EmbedVector\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Subhendu\EmbedVector\EmbedVectorServiceProvider;

use function Orchestra\Testbench\package_path;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Subhendu\\EmbedVector\\Tests\\Fixtures\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EmbedVectorServiceProvider::class,
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
                'host' => env('DB_HOST', 'localhost'),
                'port' => env('DB_PORT', 5432),
                'database' => env('DB_DATABASE', 'embedvector_test'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'postgres'),
            ]
        );
    }
}
