<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only run pgvector-specific statements on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('CREATE INDEX IF NOT EXISTS embeddings_embedding_cosine_idx ON embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);');
        }

        Schema::table('embeddings', function (Blueprint $table) {
            $table->index('model_type');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS embeddings_embedding_cosine_idx');
        }

        Schema::table('embeddings', function (Blueprint $table) {
            $table->dropIndex(['model_type']);
        });
    }
};
