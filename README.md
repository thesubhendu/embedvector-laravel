# EmbedVector Laravel Package

A Laravel package for building intelligent recommendation systems using OpenAI embeddings. Perfect for creating personalized content recommendations, job matching, product suggestions, and similar features where you need to find relevant matches based on user profiles or content similarity.

## Features

- Batch embedding processing using OpenAI's batch API
- Separate database connection support for vector operations
- Automatic vector extension creation for PostgreSQL
- Efficient batch processing with configurable chunk sizes
- **Dual Contract System**: Separate contracts for embedding generation and searchable models
- **Smart Model Separation**: Models can be either embedding sources or searchable targets

## Installation

1. Install the package via Composer:
```bash
composer require thesubhendu/embedvector-laravel
```

2. Publish the configuration and migrations:
```bash
php artisan vendor:publish --provider="Subhendu\EmbedVector\EmbedVectorServiceProvider"
```

3. Configure your environment variables:
```env
OPENAI_API_KEY=your_openai_api_key_here
```
**Database Requirements**: This package requires PostgreSQL with the pgvector extension for vector operations. 

Optional: If you want to use a separate PostgreSQL database connection other than your application database for vector operations, you can set the `EMBEDVECTOR_DB_CONNECTION` environment variable.
```env
EMBEDVECTOR_DB_CONNECTION=pgsql
```

4. Run the migrations 
```bash
php artisan migrate 
```

## Usage

### Understanding the Contract System

This package uses two distinct contracts to separate concerns based on the direction of matching:

1. **`EmbeddableContract`** - For models that generate embeddings (e.g., Customer/Candidate profiles)
2. **`EmbeddingSearchableContract`** - For models that can be found using embeddings (e.g., Jobs)

#### Example Use Case: Job Matching for Candidates

If system is designed to **find matching jobs for customers/candidates**, not the other way around:

- **Customer/Candidate** implements `EmbeddableContract` → generates embeddings from their profile, skills, preferences
- **Job** implements `EmbeddingSearchableContract` → can be found/recommended based on candidate embeddings
- **Flow**: Customer embeddings are used to find relevant Jobs that match their profile


**For Bidirectional Matching**: If you want both ways (finding jobs for candidates AND finding candidates for jobs), then both models need to implement `EmbeddingSearchableContract`.

### Basic Embedding

```php
use Subhendu\EmbedVector\Services\EmbeddingService;

$embeddingService = app(EmbeddingService::class);
$embedding = $embeddingService->createEmbedding('Your text here');
```

### Implementing Contracts

#### For Models That Generate Embeddings (e.g., Customer)

```php
use Illuminate\Database\Eloquent\Model;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Traits\EmbeddableTrait;

class Customer extends Model implements EmbeddableContract
{
    use EmbeddableTrait;

    public function toEmbeddingText(): string
    {
        return $this->name . ' ' . $this->department . ' ' . $this->skills;
    }
}
```

#### For Models That Can Be Searched (e.g., Job)

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Subhendu\EmbedVector\Contracts\EmbeddingSearchableContract;
use Subhendu\EmbedVector\Traits\EmbeddingSearchableTrait;

class Job extends Model implements EmbeddingSearchableContract
{
    use EmbeddingSearchableTrait;
    use HasFactory;

    public function toEmbeddingText(): string
    {
        return $this->title . ' ' . $this->description . ' ' . $this->requirements;
    }
}
```

**Note**: `EmbeddingSearchableContract` extends `EmbeddableContract`, and `EmbeddingSearchableTrait` automatically includes `EmbeddableTrait` functionality, so you only need to use one trait.


### Finding Matching Results

#### Basic Usage

```php
// Find jobs that match a customer's profile
$customer = Customer::find(1);
$matchingJobs = $customer->matchingResults(Job::class, 10);

foreach ($matchingJobs as $job) {
    echo "Job: {$job->title} - Match: {$job->match_percent}%";
    echo "Distance: {$job->distance}";
}
```

#### Advanced Usage with Filters

You can add query filters to narrow down the search results before embedding similarity is calculated:

```php
// Find only active jobs in specific locations
$customer = Customer::find(1);
$matchingJobs = $customer->matchingResults(
    targetModelClass: Job::class,
    topK: 10,
    queryFilter: function ($query) {
        $query->where('status', 'active')
              ->whereIn('location', ['New York', 'San Francisco'])
              ->where('salary', '>=', 80000);
    }
);
```

#### Method Parameters

- **`targetModelClass`** (string): The class name of the model you want to find matches for
- **`topK`** (int, default: 5): Maximum number of results to return
- **`queryFilter`** (Closure, optional): Custom query constraints to apply before similarity matching

#### Return Properties

Each returned model includes additional properties:
- **`match_percent`** (float): Similarity percentage (0-100, higher is better)
- **`distance`** (float): Vector distance (lower is better for similarity)

## Configuration

The package publishes a configuration file to `config/embedvector.php` with the following options:

```php
return [
    'openai_api_key' => env('OPENAI_API_KEY', ''),
    'embedding_model' => env('EMBEDVECTOR_MODEL', 'text-embedding-3-small'),
    'distance_metric' => env('EMBEDVECTOR_DISTANCE', 'cosine'), // cosine | l2
    'search_strategy' => env('EMBEDVECTOR_SEARCH_STRATEGY', 'auto'), // auto | optimized | cross_connection
    'lot_size' => env('EMBEDVECTOR_LOT_SIZE', 50000),
    'chunk_size' => env('EMBEDVECTOR_CHUNK_SIZE', 500),
    'directories' => [
        'input' => 'embeddings/input',
        'output' => 'embeddings/output',
    ],
    'database_connection' => env('EMBEDVECTOR_DB_CONNECTION', 'pgsql'),
];
```

### Configuration Options Explained

- **`openai_api_key`**: Your OpenAI API key (required in production)
- **`embedding_model`**: OpenAI embedding model to use (text-embedding-3-small, text-embedding-3-large, etc.)
- **`distance_metric`**: Vector similarity calculation method
  - `cosine`: Better for semantic similarity (recommended)
  - `l2`: Euclidean distance for geometric similarity
- **`search_strategy`**: How to perform similarity searches
  - `auto`: Automatically choose the best strategy (recommended)
  - `optimized`: Use JOIN-based queries (same database only)
  - `cross_connection`: Two-step approach (works across different databases)
- **`lot_size`**: Maximum items per OpenAI batch (up to 50,000)
- **`chunk_size`**: Items processed per chunk during batch generation
- **`database_connection`**: PostgreSQL connection for vector operations

### Batch Processing

For processing large datasets efficiently, this package provides batch processing capabilities using OpenAI's batch API, which is more cost-effective for processing many embeddings at once.

## Commands

- `php artisan embedding:gen {model} {--type=sync|init} {--force}` - Generate batch embeddings for a specific model
- `php artisan embedding:proc {--batch-id=} {--all}` - Process completed batch results

### Command Options

#### `embedding:gen`
- `{model}` - The model class name to generate embeddings for (e.g. `App\\Models\\Job`)
- `--type=sync` - Processing type (default: sync)
- `--force` - Force overwrite existing files

#### `embedding:proc`
- `--batch-id=` - Process a specific batch by ID
- `--all` - Process all completed batches
- No options - Check and process batches that are ready (default behavior)

### Usage Examples

```bash
# Generate embeddings for User model (init = first time, sync = update existing)
php artisan embedding:gen "App\\Models\\User" --type=init

# Generate embeddings for sync (only models that need updates)
php artisan embedding:gen "App\\Models\\Job" --type=sync

# Check and process ready batches (default)
php artisan embedding:proc

# Process all completed batches
php artisan embedding:proc --all

# Process specific batch
php artisan embedding:proc --batch-id=batch_abc123
```

## Real-World Examples

### E-commerce Product Recommendations

```php
// Product model (searchable)
class Product extends Model implements EmbeddingSearchableContract
{
    use EmbeddingSearchableTrait;

    public function toEmbeddingText(): string
    {
        return $this->name . ' ' . $this->description . ' ' . $this->category . ' ' . $this->tags;
    }
}

// User model (generates embeddings from purchase history)
class User extends Model implements EmbeddableContract
{
    use EmbeddableTrait;

    public function toEmbeddingText(): string
    {
        $purchaseHistory = $this->orders()
            ->with('products')
            ->get()
            ->flatMap->products
            ->pluck('name')
            ->implode(' ');
            
        return $this->preferences . ' ' . $purchaseHistory;
    }
}

// Find recommended products for a user
$user = User::find(1);
$recommendations = $user->matchingResults(
    targetModelClass: Product::class,
    topK: 20,
    queryFilter: function ($query) {
        $query->where('in_stock', true)
              ->where('price', '<=', 500)
              ->whereNotIn('id', auth()->user()->purchased_product_ids);
    }
);
```

### Job Matching Platform

```php
// Find jobs for a candidate with filters
$candidate = Candidate::find(1);
$matchingJobs = $candidate->matchingResults(
    targetModelClass: Job::class,
    topK: 15,
    queryFilter: function ($query) use ($candidate) {
        $query->where('status', 'open')
              ->where('remote_allowed', $candidate->prefers_remote)
              ->whereIn('experience_level', $candidate->acceptable_levels)
              ->where('salary_min', '>=', $candidate->min_salary);
    }
);

foreach ($matchingJobs as $job) {
    echo "Match: {$job->match_percent}% - {$job->title} at {$job->company}";
}
```

### Content Recommendation System

```php
// Article model
class Article extends Model implements EmbeddingSearchableContract
{
    use EmbeddingSearchableTrait;

    public function toEmbeddingText(): string
    {
        return $this->title . ' ' . $this->summary . ' ' . $this->tags . ' ' . $this->category;
    }
}

// User reading history model
class UserProfile extends Model implements EmbeddableContract
{
    use EmbeddableTrait;

    public function toEmbeddingText(): string
    {
        $readingHistory = $this->user->readArticles()
            ->selectRaw('GROUP_CONCAT(title, " ", summary) as content')
            ->value('content');
            
        return $this->interests . ' ' . $readingHistory;
    }
}

// Get personalized article recommendations
$profile = UserProfile::where('user_id', auth()->id())->first();
$recommendations = $profile->matchingResults(
    targetModelClass: Article::class,
    topK: 10,
    queryFilter: function ($query) use ($profile) {
        $query->where('published', true)
              ->where('created_at', '>=', now()->subDays(7))
              ->whereNotIn('id', $profile->user->read_article_ids);
    }
);
```

## Best Practices

### 1. Optimize Your `toEmbeddingText()` Method

```php
public function toEmbeddingText(): string
{
    // ✅ Good: Concise, relevant information
    return trim($this->title . ' ' . $this->description . ' ' . $this->tags);
    
    // ❌ Avoid: Too much noise or irrelevant data
    // return $this->created_at . ' ' . $this->id . ' ' . $this->long_legal_text;
}
```

### 2. Use Appropriate Filters

```php
// ✅ Good: Filter before similarity calculation
$matches = $user->matchingResults(
    Product::class,
    10,
    fn($q) => $q->where('available', true)->where('price', '<=', $budget)
);

// ❌ Less efficient: Filtering after embedding calculation
$allMatches = $user->matchingResults(Product::class, 100);
$filtered = $allMatches->where('available', true);
```

### 3. Manage Embedding Sync

```php
// Trigger re-embedding when relevant data changes
class Job extends Model implements EmbeddingSearchableContract 
{
    use EmbeddingSearchableTrait;
    
    protected static function booted()
    {
        static::updated(function ($job) {
            if ($job->isDirty(['title', 'description', 'requirements'])) {
                $job->embedding()->update(['embedding_sync_required' => true]);
            }
        });
    }
}
```

## Troubleshooting

### Common Issues

1. **"No embedding found in response"**
   - Check your OpenAI API key is valid
   - Verify the embedding model exists
   - Ensure your `toEmbeddingText()` returns non-empty strings

2. **"Model class must implement EmbeddingSearchableContract"**
   - Target models must implement `EmbeddingSearchableContract`
   - Source models only need `EmbeddableContract`

3. **Poor matching results**
   - Review your `toEmbeddingText()` method - it should contain relevant, semantic information
   - Consider using `cosine` distance for semantic similarity
   - Try different embedding models (text-embedding-3-large for better quality)

4. **Performance issues**
   - Use batch processing for large datasets
   - Consider using `optimized` search strategy for same-database scenarios
   - Add appropriate database indexes

### Database Performance

```sql
-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS embeddings_model_type_idx ON embeddings (model_type);
CREATE INDEX IF NOT EXISTS embeddings_sync_required_idx ON embeddings (embedding_sync_required);
```

## Environment Variables Reference

Add these to your `.env` file:

```env
# Required
OPENAI_API_KEY=your_openai_api_key_here

# Optional - Customize behavior
EMBEDVECTOR_MODEL=text-embedding-3-small
EMBEDVECTOR_DISTANCE=cosine
EMBEDVECTOR_SEARCH_STRATEGY=auto
EMBEDVECTOR_LOT_SIZE=50000
EMBEDVECTOR_CHUNK_SIZE=500
EMBEDVECTOR_DB_CONNECTION=pgsql
```

## Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Source Model  │    │  Target Model   │    │   Embeddings    │
│ (EmbeddableContract)  │ (EmbeddingSearchableContract) │    │    Table       │
│                 │    │                 │    │                 │
│ • Customer      │───▶│ • Job           │◀──│ • Vector data   │
│ • User Profile  │    │ • Product       │   │ • Similarity    │
│ • Candidate     │    │ • Article       │   │   calculations  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ toEmbeddingText()│    │ toEmbeddingText()│    │  PostgreSQL     │
│ • Generate text │    │ • Generate text │    │  with pgvector  │
│   for embedding │    │   for embedding │    │  extension      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## API Reference

### EmbeddableTrait Methods

#### `matchingResults(string $targetModelClass, int $topK = 5, ?Closure $queryFilter = null): Collection`

Find models similar to the current model.

**Parameters:**
- `$targetModelClass`: Fully qualified class name of the target model
- `$topK`: Maximum number of results (default: 5)
- `$queryFilter`: Optional closure to filter results before similarity calculation

**Returns:** Collection of models with `match_percent` and `distance` properties

#### `getEmbedding(): Embedding`

Get or create the embedding for the current model.

**Returns:** Embedding model instance

#### `embedding(): MorphOne`

Eloquent relationship to the embedding record.

**Returns:** MorphOne relationship

### EmbeddingSearchableTrait Methods

#### `queryForEmbedding(): Builder`

Get the base query for models to be embedded during initial processing.

**Returns:** Eloquent Builder instance

#### `queryForSyncing(): Builder`

Get the query for models that need re-embedding (sync process).

**Returns:** Eloquent Builder instance

### Configuration Methods

#### `getConnectionName(): ?string`

Get the database connection name for the model.

**Returns:** Database connection name or null for default

## Testing

The package includes comprehensive tests. Run them with:

```bash
# Run all tests
vendor/bin/pest

# Run with coverage (requires Xdebug)
vendor/bin/pest --coverage

# Run static analysis
vendor/bin/phpstan analyse
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for your changes
5. Ensure all tests pass (`vendor/bin/pest`)
6. Run static analysis (`vendor/bin/phpstan analyse`)
7. Commit your changes (`git commit -m 'Add amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Subhendu Bhatta](https://github.com/thesubhendu)
- Built with [Laravel](https://laravel.com)
- Powered by [OpenAI Embeddings](https://platform.openai.com/docs/guides/embeddings)
- Uses [pgvector](https://github.com/pgvector/pgvector) for PostgreSQL vector operations


