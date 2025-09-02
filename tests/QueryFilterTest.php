<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pgvector\Laravel\Vector;
use Subhendu\EmbedVector\Models\Embedding;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Customer;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Job;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clean up any existing data for test isolation
    Embedding::query()->delete();
    Job::query()->delete();
    Customer::query()->delete();
});

describe('Query Filter Logic', function () {
    it('applies basic where clause filters correctly', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs with different statuses
        $activeJobs = Job::factory()->count(3)->create([
            'department' => 'Engineering',
            'title' => 'Active Job'
        ]);
        $inactiveJobs = Job::factory()->count(2)->create([
            'department' => 'Engineering', 
            'title' => 'Inactive Job'
        ]);
        
        // Create embeddings for all jobs
        foreach ($activeJobs->merge($inactiveJobs) as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Filter for only active jobs
        $matches = $customer->matchingResults(
            Job::class, 
            10, 
            fn($query) => $query->where('title', 'Active Job')
        );
        
        expect($matches)->toHaveCount(3);
        
        foreach ($matches as $match) {
            expect($match->title)->toBe('Active Job');
        }
    });
    
    it('applies complex multi-condition filters', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs with various combinations of criteria
        $matchingJobs = Job::factory()->count(2)->create([
            'department' => 'Engineering',
            'title' => 'Senior Engineer'
        ]);
        $nonMatchingJobs1 = Job::factory()->count(2)->create([
            'department' => 'Marketing', // Wrong department
            'title' => 'Senior Engineer'
        ]);
        $nonMatchingJobs2 = Job::factory()->count(1)->create([
            'department' => 'Engineering',
            'title' => 'Junior Engineer' // Wrong title
        ]);
        
        // Create embeddings for all jobs
        $allJobs = $matchingJobs->merge($nonMatchingJobs1)->merge($nonMatchingJobs2);
        foreach ($allJobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Apply complex filter
        $matches = $customer->matchingResults(
            Job::class,
            10,
            function ($query) {
                $query->where('department', 'Engineering')
                      ->where('title', 'Senior Engineer');
            }
        );
        
        expect($matches)->toHaveCount(2);
        
        foreach ($matches as $match) {
            expect($match->department)->toBe('Engineering');
            expect($match->title)->toBe('Senior Engineer');
        }
    });
    
    it('applies whereIn filters correctly', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs in different departments
        $engineeringJobs = Job::factory()->count(2)->create(['department' => 'Engineering']);
        $marketingJobs = Job::factory()->count(2)->create(['department' => 'Marketing']);
        $salesJobs = Job::factory()->count(2)->create(['department' => 'Sales']);
        $hrJobs = Job::factory()->count(1)->create(['department' => 'HR']);
        
        // Create embeddings for all jobs
        $allJobs = $engineeringJobs->merge($marketingJobs)->merge($salesJobs)->merge($hrJobs);
        foreach ($allJobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Filter for specific departments
        $matches = $customer->matchingResults(
            Job::class,
            10,
            fn($query) => $query->whereIn('department', ['Engineering', 'Marketing'])
        );
        
        expect($matches)->toHaveCount(4);
        
        $departments = $matches->pluck('department')->unique()->sort()->values();
        expect($departments->toArray())->toBe(['Engineering', 'Marketing']);
    });
    
    it('applies whereNotIn filters correctly', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs in different departments
        $engineeringJobs = Job::factory()->count(2)->create(['department' => 'Engineering']);
        $marketingJobs = Job::factory()->count(2)->create(['department' => 'Marketing']);
        $salesJobs = Job::factory()->count(2)->create(['department' => 'Sales']);
        
        // Create embeddings for all jobs
        $allJobs = $engineeringJobs->merge($marketingJobs)->merge($salesJobs);
        foreach ($allJobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Exclude specific departments
        $matches = $customer->matchingResults(
            Job::class,
            10,
            fn($query) => $query->whereNotIn('department', ['Sales'])
        );
        
        expect($matches)->toHaveCount(4);
        
        foreach ($matches as $match) {
            expect($match->department)->not->toBe('Sales');
        }
    });
    
    it('handles date range filters', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs with different creation dates
        $recentJobs = Job::factory()->count(3)->create([
            'department' => 'Engineering',
            'created_at' => now()->subDays(5)
        ]);
        $oldJobs = Job::factory()->count(2)->create([
            'department' => 'Engineering',
            'created_at' => now()->subDays(15)
        ]);
        
        // Create embeddings for all jobs
        $allJobs = $recentJobs->merge($oldJobs);
        foreach ($allJobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Filter for recent jobs (last 7 days)
        $matches = $customer->matchingResults(
            Job::class,
            10,
            fn($query) => $query->where('created_at', '>=', now()->subDays(7))
        );
        
        expect($matches)->toHaveCount(3);
        
        foreach ($matches as $match) {
            expect($match->created_at->diffInDays(now()))->toBeLessThanOrEqual(7);
        }
    });
    
    it('applies null and not null filters', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs with and without titles
        $jobsWithTitle = Job::factory()->count(3)->create([
            'department' => 'Engineering',
            'title' => 'Software Engineer'
        ]);
        $jobsWithoutTitle = Job::factory()->count(2)->create([
            'department' => 'Engineering',
            'title' => null
        ]);
        
        // Create embeddings for all jobs
        $allJobs = $jobsWithTitle->merge($jobsWithoutTitle);
        foreach ($allJobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Filter for jobs with titles
        $matchesWithTitle = $customer->matchingResults(
            Job::class,
            10,
            fn($query) => $query->whereNotNull('title')
        );
        
        expect($matchesWithTitle)->toHaveCount(3);
        
        foreach ($matchesWithTitle as $match) {
            expect($match->title)->not->toBeNull();
        }
        
        // Filter for jobs without titles
        $matchesWithoutTitle = $customer->matchingResults(
            Job::class,
            10,
            fn($query) => $query->whereNull('title')
        );
        
        expect($matchesWithoutTitle)->toHaveCount(2);
        
        foreach ($matchesWithoutTitle as $match) {
            expect($match->title)->toBeNull();
        }
    });
    
    it('respects topK limit even with filters', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create many matching jobs
        $jobs = Job::factory()->count(10)->create([
            'department' => 'Engineering',
            'title' => 'Engineer'
        ]);
        
        // Create embeddings for all jobs
        foreach ($jobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Request only 5 results with filter
        $matches = $customer->matchingResults(
            Job::class,
            5, // topK limit
            fn($query) => $query->where('title', 'Engineer')
        );
        
        expect($matches)->toHaveCount(5); // Should respect topK limit
        
        foreach ($matches as $match) {
            expect($match->title)->toBe('Engineer');
        }
    });
    
    it('returns empty results when filter matches no records', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs that won't match the filter
        $jobs = Job::factory()->count(3)->create([
            'department' => 'Engineering',
            'title' => 'Engineer'
        ]);
        
        // Create embeddings for all jobs
        foreach ($jobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Filter for non-existent criteria
        $matches = $customer->matchingResults(
            Job::class,
            10,
            fn($query) => $query->where('title', 'Non-existent Title')
        );
        
        expect($matches)->toBeEmpty();
    });
    
    it('works without any filters (null filter)', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs
        $jobs = Job::factory()->count(4)->create(['department' => 'Engineering']);
        
        // Create embeddings for all jobs
        foreach ($jobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // No filter provided (should return all)
        $matches = $customer->matchingResults(Job::class, 10);
        
        expect($matches)->toHaveCount(4);
    });
    
    it('applies ordering within filters correctly', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs with different creation dates
        $jobs = collect();
        for ($i = 1; $i <= 5; $i++) {
            $jobs->push(Job::factory()->create([
                'department' => 'Engineering',
                'title' => "Job {$i}",
                'created_at' => now()->subDays($i)
            ]));
        }
        
        // Create embeddings for all jobs (with slightly different vectors for ordering)
        foreach ($jobs as $index => $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1 + ($index * 0.01))),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding that's closest to the first job
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)), // Matches first job closely
            'embedding_sync_required' => false,
        ]);
        
        // Filter and expect results ordered by similarity
        $matches = $customer->matchingResults(
            Job::class,
            5,
            fn($query) => $query->where('department', 'Engineering')
        );
        
        expect($matches)->toHaveCount(5);
        
        // Verify they're ordered by similarity (distance should increase)
        $distances = $matches->pluck('distance');
        for ($i = 1; $i < $distances->count(); $i++) {
            expect($distances[$i])->toBeGreaterThanOrEqual($distances[$i - 1]);
        }
    });
    
    it('handles complex nested query conditions', function () {
        $customer = Customer::factory()->create(['department' => 'Engineering']);
        
        // Create jobs with various criteria
        $targetJobs = Job::factory()->count(2)->create([
            'department' => 'Engineering',
            'title' => 'Senior Engineer'
        ]);
        $nonTargetJobs1 = Job::factory()->count(1)->create([
            'department' => 'Marketing',
            'title' => 'Senior Engineer'
        ]);
        $nonTargetJobs2 = Job::factory()->count(1)->create([
            'department' => 'Engineering',
            'title' => 'Junior Engineer'
        ]);
        
        // Create embeddings for all jobs
        $allJobs = $targetJobs->merge($nonTargetJobs1)->merge($nonTargetJobs2);
        foreach ($allJobs as $job) {
            Embedding::create([
                'model_id' => $job->id,
                'model_type' => Job::class,
                'embedding' => new Vector(array_fill(0, 1536, 0.1)),
                'embedding_sync_required' => false,
            ]);
        }
        
        // Create customer embedding
        Embedding::create([
            'model_id' => $customer->id,
            'model_type' => Customer::class,
            'embedding' => new Vector(array_fill(0, 1536, 0.1)),
            'embedding_sync_required' => false,
        ]);
        
        // Apply nested conditions
        $matches = $customer->matchingResults(
            Job::class,
            10,
            function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('department', 'Engineering')
                             ->where('title', 'Senior Engineer');
                })->orWhere(function ($subQuery) {
                    $subQuery->where('department', 'Marketing')
                             ->where('title', 'Senior Engineer');
                });
            }
        );
        
        expect($matches)->toHaveCount(3);
        
        foreach ($matches as $match) {
            $isEngineringSenior = $match->department === 'Engineering' && $match->title === 'Senior Engineer';
            $isMarketingSenior = $match->department === 'Marketing' && $match->title === 'Senior Engineer';
            
            expect($isEngineringSenior || $isMarketingSenior)->toBeTrue();
        }
    });
});