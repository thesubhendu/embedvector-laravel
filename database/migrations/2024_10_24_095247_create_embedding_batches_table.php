<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('embedding_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique();  // The batch ID returned by OpenAI
            $table->string('input_file_id');             // The file ID from the uploaded file
            $table->string('status')->default('validating');  // https://platform.openai.com/docs/guides/batch/4-checking-the-status-of-a-batch Track the status (pending, completed, failed)
            $table->string('output_file_id')->nullable();             // Open AI result file ID
            $table->string('saved_file_path')->nullable();             // saved jsonl file
            $table->string('embeddable_model')->nullable();             // saved jsonl file
            $table->text('error_message')->nullable();     // Optional field for error messages
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embedding_batches');
    }
};
