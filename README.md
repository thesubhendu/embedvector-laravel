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

### Implementing Contracts

#### For Models That Generate Embeddings (e.g., Customer)

```php
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


### Understanding the Contract System

This package uses two distinct contracts to separate concerns:

1. **`EmbeddableContract`** - For models that generate embeddings (e.g., Customer profiles)
2. **`EmbeddingSearchableContract`** - For models that can be found using embeddings (e.g., Jobs)

#### Example Use Case
- **Customer** implements `EmbeddableContract` → generates embeddings for personalization
- **Job** implements both contracts → can be embedded AND searched
- Customer embeddings are used to find matching Jobs

### Basic Embedding

```php
use Subhendu\EmbedVector\Services\EmbeddingService;

$embeddingService = app(EmbeddingService::class);
$embedding = $embeddingService->createEmbedding('Your text here');
```


### Finding Matching Results

```php
// Find jobs that match a customer's profile
$customer = Customer::find(1);
$matchingJobs = $customer->matchingResults(JobVerified::class, 10);

foreach ($matchingJobs as $job) {
    echo "Job: {$job->title} - Match: {$job->match_percent}%";
}
```

### Batch Processing

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
# Generate embeddings for User model
php artisan embedding:gen "App\\Models\\User" --type=sync 

# Check and process ready batches (default)
php artisan embedding:proc
```


