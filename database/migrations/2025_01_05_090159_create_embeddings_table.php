<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->getConnection())->create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->vector('embedding', 1536); // Dimensionality; 1536 for OpenAI's ada-002
            $table->morphs('model');
            $table->unique(['model_id', 'model_type'], 'embeddings_model_id_model_type_unique');
            $table->boolean('embedding_sync_required')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('embeddings');
    }
};
