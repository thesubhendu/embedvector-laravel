<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->vector('embedding', config('embedvector.embedding_dimensions')); // Dimensionality; 1536 for OpenAI's ada-002
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
        Schema::dropIfExists('embeddings');
    }
};
