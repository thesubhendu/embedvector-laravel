<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage ;
use OpenAI\Client;
use OpenAI\Testing\ClientFake;
use Pgvector\Laravel\Vector;
use Subhendu\Recommender\Models\Embedding;
use Subhendu\Recommender\Models\EmbeddingBatch;
use Subhendu\Recommender\Services\BatchEmbeddingService;
use Subhendu\Recommender\Services\ProcessCompletedBatchService;
use Subhendu\Recommender\Tests\Fixtures\Models\Customer;
use \Subhendu\Recommender\Tests\Fixtures\Models\Job;

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
   // create embeddings for customers and jobs
    $customerEmbeddings = [
        '1' => padVector([0.1, 0.2, 0.3]), // Customer with ID 1
        '2' => padVector([0.1, 0.2, 0.3]), // Customer with ID 2
        '3' => padVector([0.1, 0.2, 0.3]), // Customer with ID 3
        '4' => padVector([0.1, 0.2, 0.3]), // Customer with ID 4
        '5' => padVector([0.1, 0.2, 0.3]), // Customer with ID 5
        '6' => padVector([0.4, 0.5, 0.6]), // Customer with ID 6
        '7' => padVector([0.4, 0.5, 0.6]), // Customer with ID 7
        '8' => padVector([0.7, 0.8, 0.9]), // Customer with ID 8
        '9' => padVector([0.7, 0.8, 0.99]), // Customer with ID 9
        '10' => padVector([0.7, 0.8, 0.999]), // Customer with ID 10
    ];

    $jobEmbeddings = [
        '1' => padVector([0.1, 0.2, 0.3]), // Job with ID 1
        '2' => padVector([0.1, 0.2, 0.3]), // Job with ID 2
        '3' => padVector([0.1, 0.2, 0.3]), // Job with ID 3
        '4' => padVector([0.1, 0.2, 0.3]), // Job with ID 4
        '5' => padVector([0.1, 0.2, 0.3]), // Job with ID 5
        '6' => padVector([0.4, 0.5, 0.6]), // Job with ID 6
        '7' => padVector([0.4, 0.5, 0.6]), // Job with ID 7
        '8' => padVector([0.7, 0.8, 0.99]), // Job with ID 8
        '9' => padVector([0.7, 0.8, 0.999]), // Job with ID 9
        '10' => padVector([0.7, 0.8, 1.0]), // Job with ID 10
    ];

   Job::factory()->count(10)->create()->each(function ($job) use ($jobEmbeddings) {
       Embedding::create([
           'model_id' => $job->id,
           'model_type' => Job::class,
           'embedding' => $jobEmbeddings[$job->id],
           'embedding_sync_required' => false,
       ]);
   });

   Customer::factory()->count(10)->create()->each(function ($customer) use ($customerEmbeddings) {
       Embedding::create([
           'model_id' => $customer->id,
           'model_type' => Customer::class,
           'embedding' => $customerEmbeddings[$customer->id],
           'embedding_sync_required' => false,
       ]);
   });

   $customer = Customer::find(1);
   $job = Job::find(1);


    $matchingJobs = $customer->matchingResults(Job::class);
    $matchingCustomers = $job->matchingResults(Customer::class);

    expect($matchingJobs->first())->toBeInstanceOf(Job::class);
    expect($matchingCustomers->first())->toBeInstanceOf(Customer::class);

    expect($matchingJobs->pluck('id')->toArray())->toEqual([1, 2, 3, 4, 5]);
    expect($matchingCustomers->pluck('id')->toArray())->toEqual([1, 2, 3, 4, 5]);
//
   $nonMatchingCustomer = Customer::find(6);
   $nonMatchingJob = Job::find(6);

   expect($nonMatchingJob->matchingResults(Customer::class))->toBeTruthy();
   expect($nonMatchingJob->matchingResults(Customer::class)->pluck('id')->toArray())->not->toContain([1]);

   expect($nonMatchingCustomer->matchingResults(Job::class))->toBeTruthy();
   expect($nonMatchingCustomer->matchingResults(Job::class)->pluck('id')->toArray())->not->toContain([1]);

});

function padVector(array $vector, int $targetDimensions = 1536): array
{
    return array_pad($vector, $targetDimensions, 0.0);
}
