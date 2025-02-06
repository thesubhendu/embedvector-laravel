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

it('generates jsonl file', function () {
    app()->bind(Client::class, fn () => new ClientFake([
        OpenAI\Responses\Files\CreateResponse::fake([
            'id' => 'file-abc123',
            'object' => 'file',
            'purpose' => 'batch',
            'status' => 'uploaded',
        ]),
        OpenAI\Responses\Batches\BatchResponse::fake([
            'id' => 'testbatchid',
            'object' => 'batch',
            'status' => 'validating',
        ]),
    ]));

    Job::factory()->count(10)->create();
    Customer::factory()->count(10)->create();

    Storage::fake('local');
    $storageDisk = Storage::disk('local');

    $batchEmbeddingService = app()->make(BatchEmbeddingService::class, ['embeddableModelName' => Customer::class, 'type' => 'init']);
    $batchEmbeddingService->generateJsonLFile();

    $storageDisk->assertExists($batchEmbeddingService->getInputFileName());

    $content = $storageDisk->get($batchEmbeddingService->getInputFileName());
    $records = array_filter(explode("\n", $content));

    expect(count($records))->toEqual(Customer::count());

    $path = $storageDisk->path($batchEmbeddingService->getInputFileName());
    $response = $batchEmbeddingService->uploadFileForBatchEmbedding($path);

    expect($response->id)->toEqual('testbatchid');

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
            'embeddable_model' => Customer::class,
            'status' => 'completed',
        ]
    );

    app(ProcessCompletedBatchService::class)->process($batch);

    expect(Embedding::count())->toBe(50);

    $embedding = Embedding::first();

    expect($embedding->embedding)->toBeInstanceOf(Vector::class);

});

it('gives correct matching results', closure: function () {
    // Create models first to get their actual IDs
    $jobs = Job::factory()->count(10)->create();
    $customers = Customer::factory()->count(10)->create();

    // Create embedding vectors with different patterns
    $similarGroup1 = padVector([0.1, 0.2, 0.3]); // First group of similar vectors
    $similarGroup2 = padVector([0.4, 0.5, 0.6]); // Second group
    $similarGroup3 = padVector([0.7, 0.8, 0.9]); // Third group with slight variations

    // Create embeddings for customers
    foreach ($customers as $index => $customer) {
        $vector = match (true) {
            $index < 5 => $similarGroup1,
            $index < 7 => $similarGroup2,
            default => padVector([0.7, 0.8, 0.9 + ($index - 7) * 0.01])
        };

        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => $vector,
            'embedding_sync_required' => false,
        ]);
    }

    // Create embeddings for jobs
    foreach ($jobs as $index => $job) {
        $vector = match (true) {
            $index < 5 => $similarGroup1,
            $index < 7 => $similarGroup2,
            default => padVector([0.7, 0.8, 0.9 + ($index - 7) * 0.01])
        };

        Embedding::create([
            'model_id' => $job->id,
            'model_type' => Job::class,
            'embedding' => $vector,
            'embedding_sync_required' => false,
        ]);
    }

    // Test similar embeddings in group 1
    $firstCustomer = $customers[0];
    $firstJob = $jobs[0];

    $matchingJobs = $firstCustomer->matchingResults(Job::class);
    $matchingCustomers = $firstJob->matchingResults(Customer::class);

    expect($matchingJobs->first())->toBeInstanceOf(Job::class);
    expect($matchingCustomers->first())->toBeInstanceOf(Customer::class);

    // Test that first 5 models match (they share similarGroup1 vector)
    expect($matchingJobs->take(5)->pluck('id'))->toEqual($jobs->take(5)->pluck('id'));
    expect($matchingCustomers->take(5)->pluck('id'))->toEqual($customers->take(5)->pluck('id'));

    // Test different group (similarGroup2)
    $differentGroupCustomer = $customers[5];
    $differentGroupJob = $jobs[5];

    $differentMatchingJobs = $differentGroupCustomer->matchingResults(Job::class);
    $differentMatchingCustomers = $differentGroupJob->matchingResults(Customer::class);

    // Verify that group 2 matches don't include group 1 IDs
    expect($differentMatchingJobs->take(5)->pluck('id'))->not->toContain($jobs[0]->id);
    expect($differentMatchingCustomers->take(5)->pluck('id'))->not->toContain($customers[0]->id);
});

function padVector(array $vector, int $targetDimensions = 1536): array
{
    return array_pad($vector, $targetDimensions, 0.0);
}
