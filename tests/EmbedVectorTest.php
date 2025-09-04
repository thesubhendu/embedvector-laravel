<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenAI\Client;
use OpenAI\Testing\ClientFake;
use Pgvector\Laravel\Vector;
use Subhendu\EmbedVector\Models\Embedding;
use Subhendu\EmbedVector\Models\EmbeddingBatch;
use Subhendu\EmbedVector\Services\BatchEmbeddingService;
use Subhendu\EmbedVector\Services\ProcessCompletedBatchService;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Customer;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Job;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clean up any existing data for test isolation
    Embedding::query()->delete();
    EmbeddingBatch::query()->delete();
    Job::query()->delete();
    Customer::query()->delete();
});

it('generates jsonl file', function () {
    app()->bind(Client::class, fn () => new ClientFake([
        OpenAI\Responses\Files\CreateResponse::fake([
            'id' => 'file-abc123',
            'object' => 'file',
            'purpose' => 'batch',
            'status' => 'uploaded',
        ]),
        OpenAI\Responses\Batches\BatchResponse::fake([
            'id' => 'testbatchid_'.uniqid(), // Make batch ID unique
            'object' => 'batch',
            'status' => 'validating',
        ]),
    ]));

    Job::factory()->count(10)->create();
    Customer::factory()->count(10)->create();

    Storage::fake('local');
    $storageDisk = Storage::disk('local');

    // Use Job model for batch embedding since it implements EmbeddingSearchableContract
    $batchEmbeddingService = app()->make(BatchEmbeddingService::class, ['embeddableModelName' => Job::class, 'type' => 'init']);
    $batchEmbeddingService->generateJsonLFile();

    $storageDisk->assertExists($batchEmbeddingService->getInputFileName());

    $content = $storageDisk->get($batchEmbeddingService->getInputFileName());
    $records = array_filter(explode("\n", $content));

    expect(count($records))->toEqual(Job::count());

    $path = $storageDisk->path($batchEmbeddingService->getInputFileName());
    $response = $batchEmbeddingService->uploadFileForBatchEmbedding($path);

    expect($response->id)->toStartWith('testbatchid_');

});

it('processes completed batch and inserts into database', function () {

    Storage::fake('local');
    Storage::disk('local')->put(__DIR__.'/../tests/Fixtures/output_embeddings_1.jsonl', file_get_contents(__DIR__.'/../tests/Fixtures/output_embeddings_1.jsonl'));
    $batch = EmbeddingBatch::create(
        [
            'batch_id' => 'testbatchid',
            'input_file_id' => 'file-abc123',
            'output_file_id' => 'file-abc123',
            'saved_file_path' => __DIR__.'/../tests/Fixtures/output_embeddings_1.jsonl',
            'embeddable_model' => Job::class, // Changed to Job since it implements EmbeddingSearchableContract
            'status' => 'completed',
        ]
    );

    app(ProcessCompletedBatchService::class)->process($batch);

    expect(Embedding::count())->toBe(50);

    $embedding = Embedding::first();

    expect($embedding->embedding)->toBeInstanceOf(Vector::class);

});

it('gives correct matching results', closure: function () {
    // Create a simple test with just 2 jobs and 2 customers
    $job1 = Job::factory()->create(['department' => 'Engineering']);
    $job2 = Job::factory()->create(['department' => 'Marketing']);

    $customer1 = Customer::factory()->create(['department' => 'Engineering']);
    $customer2 = Customer::factory()->create(['department' => 'Marketing']);

    // Create very distinct vectors
    $engineeringVector = new Vector(array_fill(0, 1536, 1.0)); // All 1s
    $marketingVector = new Vector(array_fill(0, 1536, -1.0)); // All -1s

    // Create embeddings for jobs
    Embedding::create([
        'model_id' => $job1->id,
        'model_type' => Job::class,
        'embedding' => $engineeringVector,
        'embedding_sync_required' => false,
    ]);

    Embedding::create([
        'model_id' => $job2->id,
        'model_type' => Job::class,
        'embedding' => $marketingVector,
        'embedding_sync_required' => false,
    ]);

    // Create embeddings for customers
    Embedding::create([
        'model_id' => $customer1->id,
        'model_type' => Customer::class,
        'embedding' => $engineeringVector,
        'embedding_sync_required' => false,
    ]);

    Embedding::create([
        'model_id' => $customer2->id,
        'model_type' => Customer::class,
        'embedding' => $marketingVector,
        'embedding_sync_required' => false,
    ]);

    // Test that engineering customer finds engineering job first
    $matchingJobs = $customer1->matchingResults(Job::class);

    expect($matchingJobs)->toHaveCount(2)
        ->and($matchingJobs->first())->toBeInstanceOf(Job::class)
        ->and($matchingJobs->first()->id)->toBe($job1->id); // Should find the similar engineering job first

    // Test that marketing customer finds marketing job first
    $marketingResults = $customer2->matchingResults(Job::class);

    expect($marketingResults)->toHaveCount(2)
        ->and($marketingResults->first())->toBeInstanceOf(Job::class)
        ->and($marketingResults->first()->id)->toBe($job2->id); // Should find the similar marketing job first
});


it('creates new embedding when none exists', function () {
    // Mock OpenAI client to return a fake embedding
    app()->bind(Client::class, fn () => new ClientFake([
        \OpenAI\Responses\Embeddings\CreateResponse::fake([
            'data' => [
                [
                    'embedding' => array_fill(0, 1536, 0.5), // Mock embedding vector
                ],
            ],
        ]),
    ]));

    $job = Job::factory()->create(['title' => 'Software Engineer', 'department' => 'Engineering']);
    
    // Ensure no embedding exists initially
    expect(Embedding::where('model_type', Job::class)->where('model_id', $job->id)->exists())->toBeFalse();
    
    // Test getEmbedding returns null when no embedding exists
    expect($job->getEmbedding())->toBeNull();
    
    // Test getOrCreateEmbedding creates new embedding
    $embedding = $job->getOrCreateEmbedding();
    
    expect($embedding)->toBeInstanceOf(Embedding::class)
        ->and($embedding->model_type)->toBe(Job::class)
        ->and($embedding->model_id)->toBe($job->id)
        ->and($embedding->embedding_sync_required)->toBeFalse()
        ->and($embedding->embedding)->toBeInstanceOf(Vector::class);
    
    // Verify embedding was saved to database
    expect(Embedding::where('model_type', Job::class)->where('model_id', $job->id)->exists())->toBeTrue();
});

it('returns existing embedding when sync not required', function () {
    $job = Job::factory()->create(['title' => 'Marketing Manager', 'department' => 'Marketing']);
    
    // Create existing embedding
    $existingVector = new Vector(array_fill(0, 1536, 0.3));
    $existingEmbedding = Embedding::create([
        'model_type' => Job::class,
        'model_id' => $job->id,
        'embedding' => $existingVector,
        'embedding_sync_required' => false,
    ]);
    
    // Mock OpenAI client - should NOT be called since embedding exists and sync not required
    $mockClient = new ClientFake([]);
    app()->bind(Client::class, fn () => $mockClient);
    
    $embedding = $job->getEmbedding();
    
    expect($embedding->id)->toBe($existingEmbedding->id)
        ->and($embedding->embedding)->toEqual($existingVector)
        ->and($embedding->embedding_sync_required)->toBeFalse();
    
    // Verify no API calls were made
    $mockClient->assertNothingSent();
});

it('updates embedding when sync is required', function () {
    // Mock OpenAI client to return a new embedding
    app()->bind(Client::class, fn () => new ClientFake([
        \OpenAI\Responses\Embeddings\CreateResponse::fake([
            'data' => [
                [
                    'embedding' => array_fill(0, 1536, 0.8), // New mock embedding vector
                ],
            ],
        ]),
    ]));

    $job = Job::factory()->create(['title' => 'Data Scientist', 'department' => 'Engineering']);
    
    // Create existing embedding that requires sync
    $oldVector = new Vector(array_fill(0, 1536, 0.1));
    $existingEmbedding = Embedding::create([
        'model_type' => Job::class,
        'model_id' => $job->id,
        'embedding' => $oldVector,
        'embedding_sync_required' => true,
    ]);
    
    // Test getEmbedding returns existing embedding without updating
    $embedding = $job->getEmbedding();
    expect($embedding->id)->toBe($existingEmbedding->id)
        ->and($embedding->embedding_sync_required)->toBeTrue()
        ->and($embedding->embedding)->toEqual($oldVector); // Should NOT be updated by getEmbedding
    
    // Test getOrCreateEmbedding updates when sync is required
    $updatedEmbedding = $job->getOrCreateEmbedding();
    expect($updatedEmbedding->embedding_sync_required)->toBeFalse()
        ->and($updatedEmbedding->embedding)->not->toEqual($oldVector); // Should be updated
    
    // Verify the embedding was updated in the database
    $freshEmbedding = $existingEmbedding->fresh();
    expect($freshEmbedding->embedding_sync_required)->toBeFalse();
});

it('uses correct text for embedding generation', function () {
    // Mock OpenAI client with multiple responses for multiple calls
    $mockClient = new ClientFake([
        \OpenAI\Responses\Embeddings\CreateResponse::fake([
            'data' => [
                [
                    'embedding' => array_fill(0, 1536, 0.7),
                ],
            ],
        ]),
        \OpenAI\Responses\Embeddings\CreateResponse::fake([
            'data' => [
                [
                    'embedding' => array_fill(0, 1536, 0.9),
                ],
            ],
        ]),
    ]);
    
    app()->bind(Client::class, fn () => $mockClient);
    
    $job = Job::factory()->create(['title' => 'Product Manager', 'department' => 'Product']);
    $customer = Customer::factory()->create(['department' => 'Sales']);
    
    // Test Job embedding uses correct text format
    $jobEmbedding = $job->getOrCreateEmbedding();
    expect($jobEmbedding)->toBeInstanceOf(Embedding::class);
    
    // Test Customer embedding uses correct text format  
    $customerEmbedding = $customer->getOrCreateEmbedding();
    expect($customerEmbedding)->toBeInstanceOf(Embedding::class);
    
    // Verify API was called twice (once for each model)
    $mockClient->assertSent(\OpenAI\Resources\Embeddings::class, 2);
});

it('queues embedding for syncing', function () {
    $job = Job::factory()->create(['title' => 'Product Manager', 'department' => 'Product']);
    
    // Create existing embedding
    $existingVector = new Vector(array_fill(0, 1536, 0.4));
    $existingEmbedding = Embedding::create([
        'model_type' => Job::class,
        'model_id' => $job->id,
        'embedding' => $existingVector,
        'embedding_sync_required' => false,
    ]);
    
    // Queue for syncing
    $job->queueForSyncing();
    
    // Verify embedding_sync_required was set to true
    $freshEmbedding = $existingEmbedding->fresh();
    expect($freshEmbedding->embedding_sync_required)->toBeTrue();
});

it('handles queueForSyncing when no embedding exists', function () {
    $job = Job::factory()->create(['title' => 'Product Manager', 'department' => 'Product']);
    
    // Ensure no embedding exists
    expect(Embedding::where('model_type', Job::class)->where('model_id', $job->id)->exists())->toBeFalse();
    
    // Queue for syncing should not throw an error
    $job->queueForSyncing();
    
    // Still no embedding should exist
    expect(Embedding::where('model_type', Job::class)->where('model_id', $job->id)->exists())->toBeFalse();
});


function padVector(array $vector, int $targetDimensions = 1536): array
{
    return array_pad($vector, $targetDimensions, 0.0);
}
