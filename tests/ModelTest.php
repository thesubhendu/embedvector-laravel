<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Pgvector\Laravel\Vector;
use Subhendu\EmbedVector\Models\Embedding;
use Subhendu\EmbedVector\Models\EmbeddingBatch;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Job;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clean up any existing data for test isolation
    Embedding::query()->delete();
    EmbeddingBatch::query()->delete();
    Job::query()->delete();
});

describe('Embedding Model', function () {
    it('uses correct database connection from config', function () {
        Config::set('embedvector.database_connection', 'testing');
        
        $embedding = new Embedding();
        
        expect($embedding->getConnectionName())->toBe('testing');
        
        Config::set('embedvector.database_connection', 'pgsql'); // Reset
    });

    it('casts embedding to Vector correctly', function () {
        $vector = new Vector(array_fill(0, 1536, 0.1));
        
        $embedding = Embedding::create([
            'model_id' => 1,
            'model_type' => 'TestModel',
            'embedding' => $vector,
            'embedding_sync_required' => false,
        ]);
        
        expect($embedding->embedding)->toBeInstanceOf(Vector::class)
            ->and($embedding->embedding->toArray())->toHaveCount(1536);
    });

    it('establishes morphTo relationship properly', function () {
        $embedding = new Embedding();
        
        expect($embedding->model())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
    });

    it('handles embedding_sync_required flag correctly', function () {
        $embedding = Embedding::create([
            'model_id' => 1,
            'model_type' => 'TestModel',
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => true,
        ]);
        
        expect($embedding->embedding_sync_required)->toBeTrue();
        
        $embedding->update(['embedding_sync_required' => false]);
        
        expect($embedding->fresh()->embedding_sync_required)->toBeFalse();
    });
});

describe('EmbeddingBatch Model', function () {
    it('uses correct database connection from config', function () {
        Config::set('embedvector.database_connection', 'testing');
        
        $batch = new EmbeddingBatch();
        
        expect($batch->getConnectionName())->toBe('testing');
        
        Config::set('embedvector.database_connection', 'pgsql'); // Reset
    });

    it('creates batch with required fields', function () {
        $batch = EmbeddingBatch::create([
            'batch_id' => 'test-batch-123',
            'input_file_id' => 'file-123',
            'embeddable_model' => 'TestModel',
            'status' => 'validating',
        ]);
        
        expect($batch->batch_id)->toBe('test-batch-123')
            ->and($batch->input_file_id)->toBe('file-123')
            ->and($batch->embeddable_model)->toBe('TestModel')
            ->and($batch->status)->toBe('validating');
    });

    it('updates batch status correctly', function () {
        $batch = EmbeddingBatch::create([
            'batch_id' => 'test-batch-456',
            'input_file_id' => 'file-456',
            'embeddable_model' => 'TestModel',
            'status' => 'validating',
        ]);
        
        $batch->update(['status' => 'completed']);
        
        expect($batch->fresh()->status)->toBe('completed');
    });
});

describe('Job Model with EmbeddingSearchableContract', function () {
    it('implements required contract methods', function () {
        $job = new Job();
        
        expect(method_exists($job, 'toEmbeddingText'))->toBeTrue()
            ->and(method_exists($job, 'matchingResults'))->toBeTrue();
    });

    it('generates embedding text correctly', function () {
        $job = Job::factory()->create([
            'department' => 'Engineering'
        ]);
        
        $embeddingText = $job->toEmbeddingText();
        
        expect($embeddingText)->toContain('Engineering');
    });
});
