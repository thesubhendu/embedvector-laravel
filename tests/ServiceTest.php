<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenAI\Client;
use OpenAI\Testing\ClientFake;
use Pgvector\Laravel\Vector;
use Subhendu\EmbedVector\Models\Embedding;
use Subhendu\EmbedVector\Models\EmbeddingBatch;
use Subhendu\EmbedVector\Services\BatchEmbeddingService;
use Subhendu\EmbedVector\Services\EmbeddingService;
use Subhendu\EmbedVector\Services\ProcessCompletedBatchService;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Job;

uses(RefreshDatabase::class);

describe('EmbeddingService', function () {
    it('generates embeddings with correct model configuration', function () {
        $client = new ClientFake([
            OpenAI\Responses\Embeddings\CreateResponse::fake([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.5),
                    ],
                ],
            ]),
        ]);

        $service = new EmbeddingService($client);

        expect($service->embeddingModel)->toBe('text-embedding-3-small');

        $vector = $service->generateEmbedding('test text');

        expect($vector)->toBeInstanceOf(Vector::class)
            ->and($vector->toArray())->toHaveCount(1536)
            ->and($vector->toArray()[0])->toBe(0.5);
    });

    it('returns client instance correctly', function () {
        $client = new ClientFake([]);
        $service = new EmbeddingService($client);

        expect($service->getClient())->toBe($client);
    });
});

describe('BatchEmbeddingService', function () {
    beforeEach(function () {
        Storage::fake('local');

        app()->bind(Client::class, fn () => new ClientFake([
            OpenAI\Responses\Files\CreateResponse::fake([
                'id' => 'file-test-123',
                'object' => 'file',
                'purpose' => 'batch',
                'status' => 'uploaded',
            ]),
            OpenAI\Responses\Batches\BatchResponse::fake([
                'id' => 'batch-test-456',
                'object' => 'batch',
                'status' => 'validating',
            ]),
        ]));
    });

    it('generates JSONL file with correct format', function () {
        Job::factory()->count(5)->create();

        $service = new BatchEmbeddingService(
            embeddableModelName: Job::class,
            embeddingService: app(EmbeddingService::class),
            embeddingBatchModel: new EmbeddingBatch,
            type: 'init'
        );

        $service->generateJsonLFile();

        Storage::disk('local')->assertExists($service->getInputFileName());

        $content = Storage::disk('local')->get($service->getInputFileName());
        $lines = array_filter(explode("\n", $content));

        expect(count($lines))->toBe(5);
    });

    it('uploads file and creates batch successfully', function () {
        Job::factory()->count(3)->create();

        $service = new BatchEmbeddingService(
            embeddableModelName: Job::class,
            embeddingService: app(EmbeddingService::class),
            embeddingBatchModel: new EmbeddingBatch,
            type: 'init'
        );

        $service->generateJsonLFile();

        $filePath = Storage::disk('local')->path($service->getInputFileName());
        $response = $service->uploadFileForBatchEmbedding($filePath);

        expect($response->id)->toBe('batch-test-456');
    });
});

describe('ProcessCompletedBatchService', function () {
    it('processes completed batch and creates embeddings', function () {
        Storage::fake('local');

        $batch = EmbeddingBatch::create([
            'batch_id' => 'test-batch',
            'input_file_id' => 'file-123',
            'embeddable_model' => Job::class,
            'status' => 'completed',
            'saved_file_path' => 'test/output.jsonl',
        ]);

        // Create mock output file
        $outputContent = json_encode([
            'custom_id' => '1',
            'response' => [
                'body' => [
                    'data' => [
                        ['embedding' => array_fill(0, 1536, 0.1)],
                    ],
                ],
            ],
        ])."\n";

        Storage::disk('local')->put('test/output.jsonl', $outputContent);

        $service = new ProcessCompletedBatchService;
        $service->process($batch);

        expect(Embedding::count())->toBeGreaterThan(0);

        $embedding = Embedding::first();
        expect($embedding->embedding)->toBeInstanceOf(Vector::class);
    });
});
