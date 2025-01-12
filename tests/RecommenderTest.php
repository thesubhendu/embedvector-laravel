<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage ;
use OpenAI\Client;
use OpenAI\Testing\ClientFake;
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

   expect($embedding->embedding)->toBeTruthy();

});

it('gives correct matching results', function () {
   // create embeddings for customers and jobs

   Job::factory()->count(10)->create();
   Customer::factory()->count(10)->create();


});
