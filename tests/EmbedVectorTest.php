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
            'id' => 'testbatchid_' . uniqid(), // Make batch ID unique
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

function padVector(array $vector, int $targetDimensions = 1536): array
{
    return array_pad($vector, $targetDimensions, 0.0);
}
