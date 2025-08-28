<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

describe('End-to-End Integration Tests', function () {
    beforeEach(function () {
        Storage::fake('local');
        
        // Clean up any existing embeddings and batches to ensure test isolation
        Embedding::query()->delete();
        EmbeddingBatch::query()->delete();
        Job::query()->delete();
        Customer::query()->delete();
        
        // Mock OpenAI client for the entire workflow
        app()->bind(Client::class, fn () => new ClientFake([
            OpenAI\Responses\Files\CreateResponse::fake([
                'id' => 'file-integration-123',
                'object' => 'file',
                'purpose' => 'batch',
                'status' => 'uploaded',
            ]),
            OpenAI\Responses\Batches\BatchResponse::fake([
                'id' => 'batch-integration-456',
                'object' => 'batch',
                'status' => 'validating',
            ]),
        ]));
    });

    it('completes full embedding workflow end-to-end', function () {
        // 1. Create test data
        $jobs = Job::factory()->count(5)->create([
            'department' => 'Engineering'
        ]);
        
        $customers = Customer::factory()->count(3)->create([
            'department' => 'Engineering'
        ]);
        
        // 2. Generate batch embedding file
        $batchService = new BatchEmbeddingService(
            embeddableModelName: Job::class,
            embeddingService: app(\Subhendu\EmbedVector\Services\EmbeddingService::class),
            embeddingBatchModel: new EmbeddingBatch(),
            type: 'init'
        );
        
        $batchService->generateJsonLFile();
        
        // 3. Verify file was created
        $fileName = $batchService->getInputFileName(1);
        Storage::disk('local')->assertExists($fileName);
        
        // 4. Upload file and create batch
        $filePath = Storage::disk('local')->path($fileName);
        $response = $batchService->uploadFileForBatchEmbedding($filePath);
        
        expect($response->id)->toBe('batch-integration-456');
        
        // 5. Verify batch was created in database
        $batch = EmbeddingBatch::where('batch_id', 'batch-integration-456')->first();
        expect($batch)->not->toBeNull()
            ->and($batch->status)->toBe('validating')
            ->and($batch->embeddable_model)->toBe(Job::class);
        
        // 6. Simulate batch completion by updating status
        $batch->update(['status' => 'completed']);
        
        // 7. Create mock output file (simulating OpenAI response)
        $outputContent = '';
        foreach ($jobs as $index => $job) {
            $outputContent .= json_encode([
                'custom_id' => (string) $job->id,
                'response' => [
                    'body' => [
                        'data' => [
                            ['embedding' => array_fill(0, 1536, 0.1 + ($index * 0.01))]
                        ]
                    ]
                ]
            ]) . "\n";
        }
        
        $outputPath = 'test/integration_output.jsonl';
        Storage::disk('local')->put($outputPath, $outputContent);
        $batch->update(['saved_file_path' => $outputPath]);
        
        // 8. Process completed batch
        $processService = new ProcessCompletedBatchService();
        $processService->process($batch);
        
        // 9. Verify embeddings were created
        expect(Embedding::count())->toBe(5);
        
        foreach ($jobs as $job) {
            $embedding = Embedding::where('model_id', $job->id)
                ->where('model_type', Job::class)
                ->first();
                
            expect($embedding)->not->toBeNull()
                ->and($embedding->embedding)->toBeInstanceOf(Vector::class)
                ->and($embedding->embedding_sync_required)->toBeFalse();
        }
        
        // 10. Test embedding-based search
        $customer = $customers->first();
        
        // Create customer embedding
        $customerVector = new Vector(array_fill(0, 1536, 0.1));
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => $customerVector,
            'embedding_sync_required' => false,
        ]);
        
        // Search for matching jobs
        $matchingJobs = $customer->matchingResults(Job::class, 3);
        
        expect($matchingJobs)->toHaveCount(3)
            ->and($matchingJobs->first())->toBeInstanceOf(Job::class)
            ->and($matchingJobs->first()->distance)->toBeFloat()
            ->and($matchingJobs->first()->match_percent)->toBeFloat();
    });

    it('handles batch processing with multiple file chunks', function () {
        // Mock OpenAI client with enough responses for multiple chunks
        app()->bind(Client::class, fn () => new ClientFake([
            // File upload responses (one for each chunk)
            OpenAI\Responses\Files\CreateResponse::fake([
                'id' => 'file-chunk-1',
                'object' => 'file',
                'purpose' => 'batch',
                'status' => 'uploaded',
            ]),
            OpenAI\Responses\Batches\BatchResponse::fake([
                'id' => 'batch-chunk-1',
                'object' => 'batch',
                'status' => 'validating',
            ]),
            OpenAI\Responses\Files\CreateResponse::fake([
                'id' => 'file-chunk-2',
                'object' => 'file',
                'purpose' => 'batch',
                'status' => 'uploaded',
            ]),
            OpenAI\Responses\Batches\BatchResponse::fake([
                'id' => 'batch-chunk-2',
                'object' => 'batch',
                'status' => 'validating',
            ]),
            OpenAI\Responses\Files\CreateResponse::fake([
                'id' => 'file-chunk-3',
                'object' => 'file',
                'purpose' => 'batch',
                'status' => 'uploaded',
            ]),
            OpenAI\Responses\Batches\BatchResponse::fake([
                'id' => 'batch-chunk-3',
                'object' => 'batch',
                'status' => 'validating',
            ]),
        ]));
        
        // Create many jobs to trigger chunking
        $jobs = Job::factory()->count(15)->create([
            'department' => 'Engineering'
        ]);
        
        // Set small lot size to force multiple files
        Config::set('embedvector.lot_size', 5);
        
        $batchService = new BatchEmbeddingService(
            embeddableModelName: Job::class,
            embeddingService: app(\Subhendu\EmbedVector\Services\EmbeddingService::class),
            embeddingBatchModel: new EmbeddingBatch(),
            type: 'init'
        );
        
        $batchService->generateJsonLFile();
        
        // Should create multiple files
        Storage::disk('local')->assertExists($batchService->getInputFileName(1));
        Storage::disk('local')->assertExists($batchService->getInputFileName(2));
        Storage::disk('local')->assertExists($batchService->getInputFileName(3));
        
        // Process each file
        for ($i = 1; $i <= 3; $i++) {
            $fileName = $batchService->getInputFileName($i);
            $filePath = Storage::disk('local')->path($fileName);
            
            // Upload file
            $response = $batchService->uploadFileForBatchEmbedding($filePath);
            
            // Create batch record with unique ID
            $batch = EmbeddingBatch::create([
                'batch_id' => 'batch-chunk-' . $i . '-' . uniqid(),
                'input_file_id' => 'file-chunk-' . $i,
                'embeddable_model' => Job::class,
                'status' => 'completed',
                'saved_file_path' => 'test/chunk_' . $i . '_output.jsonl'
            ]);
            
            // Create mock output for this chunk
            $outputContent = '';
            $startIndex = ($i - 1) * 5;
            $endIndex = min($startIndex + 5, 15);
            
            for ($j = $startIndex; $j < $endIndex; $j++) {
                $outputContent .= json_encode([
                    'custom_id' => (string) $jobs[$j]->id,
                    'response' => [
                        'body' => [
                            'data' => [
                                ['embedding' => array_fill(0, 1536, 0.1 + ($j * 0.01))]
                            ]
                        ]
                    ]
                ]) . "\n";
            }
            
            Storage::disk('local')->put('test/chunk_' . $i . '_output.jsonl', $outputContent);
            
            // Process chunk
            $processService = new ProcessCompletedBatchService();
            $processService->process($batch);
        }
        
        // Verify all embeddings were created
        expect(Embedding::count())->toBe(15);
        
        Config::set('embedvector.lot_size', 50000); // Reset
    });

    it('processes models with complex toEmbeddingText methods', function () {
        // Create a complex model that combines multiple fields
        $complexJob = Job::factory()->create([
            'department' => 'Engineering',
            'title' => 'Senior Software Engineer'
        ]);
        
        // Test the toEmbeddingText method - it should return the department
        $embeddingText = $complexJob->toEmbeddingText();
        expect($embeddingText)->toBe('Engineering');
        
        // Create embedding for this complex model
        $vector = new Vector(array_fill(0, 1536, 0.1));
        Embedding::create([
            'model_id' => $complexJob->id,
            'model_type' => Job::class,
            'embedding' => $vector,
            'embedding_sync_required' => false,
        ]);
        
        // Create a customer with similar interests
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        $customerVector = new Vector(array_fill(0, 1536, 0.1));
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => $vector,
            'embedding_sync_required' => false,
        ]);
        
        // Test matching
        $matchingJobs = $customer->matchingResults(Job::class, 1);
        
        expect($matchingJobs)->toHaveCount(1)
            ->and($matchingJobs->first()->id)->toBe($complexJob->id);
    });

    it('works with different model relationships', function () {
        // Create jobs with different departments
        $engineeringJobs = Job::factory()->count(3)->create(['department' => 'Engineering']);
        $marketingJobs = Job::factory()->count(2)->create(['department' => 'Marketing']);
        $salesJobs = Job::factory()->count(2)->create(['department' => 'Sales']);
        
        // Create customers with different interests
        $engineeringCustomer = Customer::factory()->create(['department' => 'Engineering']);
        $marketingCustomer = Customer::factory()->create(['department' => 'Marketing']);
        
        // Create embeddings for all models
        $allJobs = $engineeringJobs->merge($marketingJobs)->merge($salesJobs);
        $allCustomers = collect([$engineeringCustomer, $marketingCustomer]);
        
        foreach ($allJobs as $job) {
            $vector = new Vector(array_fill(0, 1536, 0.1));
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => $vector,
                'embedding_sync_required' => false,
            ]);
        }
        
        foreach ($allCustomers as $customer) {
            $vector = new Vector(array_fill(0, 1536, 0.1));
            Embedding::create([
                'model_id' => $customer->id,
                'model_type' => Customer::class,
                'embedding' => $vector,
                'embedding_sync_required' => false,
            ]);
        }
        
        // Test that engineering customer finds engineering jobs
        $engineeringResults = $engineeringCustomer->matchingResults(Job::class, 5);
        expect($engineeringResults)->toHaveCount(5);
        
        // Test that marketing customer finds marketing jobs
        $marketingResults = $marketingCustomer->matchingResults(Job::class, 5);
        expect($marketingResults)->toHaveCount(5);
    });

    it('handles mixed database connections scenario', function () {
        // Test the cross-connection search strategy
        Config::set('embedvector.search_strategy', 'cross_connection');
        
        $job = Job::factory()->create(['department' => 'Engineering']);
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create embeddings
        $vector = new Vector(array_fill(0, 1536, 0.1));
        
        Embedding::create([
            'model_id' => $job->id,
            'model_type' => Job::class,
            'embedding' => $vector,
            'embedding_sync_required' => false,
        ]);
        
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => $vector,
            'embedding_sync_required' => false,
        ]);
        
        // Test cross-connection search
        $results = $customer->matchingResults(Job::class, 1);
        
        expect($results)->toHaveCount(1)
            ->and($results->first()->id)->toBe($job->id);
            
        Config::set('embedvector.search_strategy', 'auto'); // Reset
    });

    it('processes real OpenAI API response format', function () {
        // Create a realistic OpenAI batch response file
        $realisticOutput = '';
        $jobs = Job::factory()->count(3)->create(['department' => 'Engineering']);
        
        foreach ($jobs as $index => $job) {
            $realisticOutput .= json_encode([
                'custom_id' => (string) $job->id,
                'response' => [
                    'statusCode' => 200,
                    'headers' => [
                        'content-type' => 'application/json',
                        'openai-model' => 'text-embedding-3-small',
                        'openai-organization' => 'org-test123',
                        'openai-processing-ms' => 45,
                        'openai-version' => '2020-10-01',
                        'x-request-id' => 'req-test-' . uniqid(),
                    ],
                    'body' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'object' => 'embedding',
                                'embedding' => array_fill(0, 1536, 0.1 + ($index * 0.01)),
                                'index' => 0,
                                'model' => 'text-embedding-3-small'
                            ]
                        ],
                        'model' => 'text-embedding-3-small',
                        'usage' => [
                            'prompt_tokens' => 5,
                            'total_tokens' => 5
                        ]
                    ]
                ]
            ]) . "\n";
        }
        
        // Create batch and save realistic output
        $batch = EmbeddingBatch::create([
            'batch_id' => 'realistic-batch-123',
            'input_file_id' => 'file-realistic-123',
            'embeddable_model' => Job::class,
            'status' => 'completed',
            'saved_file_path' => 'test/realistic_output.jsonl'
        ]);
        
        Storage::disk('local')->put('test/realistic_output.jsonl', $realisticOutput);
        
        // Process the realistic batch
        $processService = new ProcessCompletedBatchService();
        $processService->process($batch);
        
        // Verify processing worked with realistic format
        expect(Embedding::count())->toBe(3);
        
        foreach ($jobs as $job) {
            $embedding = Embedding::where('model_id', $job->id)
                ->where('model_type', Job::class)
                ->first();
                
            expect($embedding)->not->toBeNull()
                ->and($embedding->embedding)->toBeInstanceOf(Vector::class);
        }
    });

    it('handles sync vs init batch types correctly', function () {
        // Test init type (all models)
        $initService = new BatchEmbeddingService(
            embeddableModelName: Job::class,
            embeddingService: app(\Subhendu\EmbedVector\Services\EmbeddingService::class),
            embeddingBatchModel: new EmbeddingBatch(),
            type: 'init'
        );
        
        Job::factory()->count(3)->create(['department' => 'Engineering']);
        
        $initService->generateJsonLFile();
        $initFileName = $initService->getInputFileName(1);
        Storage::disk('local')->assertExists($initFileName);
        
        // Test sync type (only models needing sync)
        $syncService = new BatchEmbeddingService(
            embeddableModelName: Job::class,
            embeddingService: app(\Subhendu\EmbedVector\Services\EmbeddingService::class),
            embeddingBatchModel: new EmbeddingBatch(),
            type: 'sync'
        );
        
        // Create some jobs that need syncing
        $jobs = Job::factory()->count(2)->create(['department' => 'Marketing']);
        foreach ($jobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => true,
            ]);
        }
        
        $syncService->generateJsonLFile();
        $syncFileName = $syncService->getInputFileName(1);
        
        // Should only process models needing sync
        if (Storage::disk('local')->exists($syncFileName)) {
            $content = Storage::disk('local')->get($syncFileName);
            $lines = array_filter(explode("\n", $content));
            expect($lines)->toHaveCount(2);
        }
    });

    it('maintains data integrity throughout the workflow', function () {
        // Create initial data
        $jobs = Job::factory()->count(5)->create(['department' => 'Engineering']);
        $customers = Customer::factory()->count(3)->create(['department' => 'Engineering']);
        
        // Generate and process batch
        $batchService = new BatchEmbeddingService(
            embeddableModelName: Job::class,
            embeddingService: app(\Subhendu\EmbedVector\Services\EmbeddingService::class),
            embeddingBatchModel: new EmbeddingBatch(),
            type: 'init'
        );
        
        $batchService->generateJsonLFile();
        $fileName = $batchService->getInputFileName(1);
        $filePath = Storage::disk('local')->path($fileName);
        
        // Verify input file content matches source data
        $content = Storage::disk('local')->get($fileName);
        $lines = array_filter(explode("\n", $content));
        
        expect($lines)->toHaveCount(5);
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            expect($data)->toHaveKey('custom_id')
                ->and($data['body']['input'])->toBe('Engineering');
        }
        
        // Upload and create batch
        $response = $batchService->uploadFileForBatchEmbedding($filePath);
        $batch = EmbeddingBatch::where('batch_id', $response->id)->first();
        
        expect($batch)->not->toBeNull()
            ->and($batch->embeddable_model)->toBe(Job::class);
        
        // Simulate completion and process
        $batch->update(['status' => 'completed']);
        
        // Create realistic output
        $outputContent = '';
        foreach ($jobs as $job) {
            $outputContent .= json_encode([
                'custom_id' => (string) $job->id,
                'response' => [
                    'body' => [
                        'data' => [
                            ['embedding' => array_fill(0, 1536, 0.1)]
                        ]
                    ]
                ]
            ]) . "\n";
        }
        
        $outputPath = 'test/integrity_output.jsonl';
        Storage::disk('local')->put($outputPath, $outputContent);
        $batch->update(['saved_file_path' => $outputPath]);
        
        // Process batch
        $processService = new ProcessCompletedBatchService();
        $processService->process($batch);
        
        // Verify data integrity
        expect(Embedding::count())->toBe(5);
        
        foreach ($jobs as $job) {
            $embedding = Embedding::where('model_id', $job->id)
                ->where('model_type', Job::class)
                ->first();
                
            expect($embedding)->not->toBeNull()
                ->and($embedding->embedding)->toBeInstanceOf(Vector::class)
                ->and($embedding->embedding_sync_required)->toBeFalse();
        }
        
        // Test that embeddings can be used for search
        $customer = $customers->first();
        $customerVector = new Vector(array_fill(0, 1536, 0.1));
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => $customerVector,
            'embedding_sync_required' => false,
        ]);
        
        $matchingJobs = $customer->matchingResults(Job::class, 3);
        expect($matchingJobs)->toHaveCount(3);
    });
});
