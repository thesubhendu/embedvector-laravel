<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('embedvector.database_connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run this if we're using PostgreSQL
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::connection($this->getConnection())->statement('CREATE EXTENSION IF NOT EXISTS vector');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run this if we're using PostgreSQL
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::connection($this->getConnection())->statement('DROP EXTENSION IF EXISTS vector');
        }
    }
};
