<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('embedvector.database_connection');
    }

    public function up(): void
    {
        $connection = $this->getConnection();

        // Only run pgvector-specific statements on PostgreSQL
        if (DB::connection($connection)->getDriverName() === 'pgsql') {
            DB::connection($connection)->statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS embeddings_embedding_cosine_idx ON embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);');
        }

        Schema::connection($connection)->table('embeddings', function (Blueprint $table) {
            $table->index('model_type');
        });
    }

    public function down(): void
    {
        $connection = $this->getConnection();

        if (DB::connection($connection)->getDriverName() === 'pgsql') {
            DB::connection($connection)->statement('DROP INDEX IF EXISTS embeddings_embedding_cosine_idx');
        }

        Schema::connection($connection)->table('embeddings', function (Blueprint $table) {
            $table->dropIndex(['model_type']);
        });
    }
};
