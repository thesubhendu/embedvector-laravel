# EmbedVector Laravel Package

A Laravel package for handling OpenAI embeddings with batch processing capabilities and intelligent model contracts.

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
composer require subhendu/embedvector-laravel
```

2. Publish the configuration and migrations:
```bash
php artisan vendor:publish --provider="Subhendu\EmbedVector\EmbedVectorServiceProvider"
```

3. Configure your environment variables:
```env
OPENAI_API_KEY=your_openai_api_key_here
EMBEDVECTOR_MODEL=text-embedding-3-small
EMBEDVECTOR_DISTANCE=cosine
EMBEDVECTOR_LOT_SIZE=50000
EMBEDVECTOR_CHUNK_SIZE=500
EMBEDVECTOR_DB_CONNECTION=vector_db
```

## Database Configuration

### Separate Database Connection (Recommended)

For production use, it's recommended to use a separate database connection for vector operations. This package automatically creates the necessary vector extension and tables in the specified connection.

1. Add a new database connection in your `config/database.php`:
```php
'connections' => [
    // ... your existing connections
    
    'vector_db' => [
        'driver' => 'pgsql',
        'host' => env('VECTOR_DB_HOST', '127.0.0.1'),
        'port' => env('VECTOR_DB_PORT', '5432'),
        'database' => env('VECTOR_DB_DATABASE', 'vector_db'),
        'username' => env('VECTOR_DB_USERNAME', 'vector_user'),
        'password' => env('VECTOR_DB_PASSWORD', ''),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ],
],
```

2. Set the connection in your `.env`:
```env
EMBEDVECTOR_DB_CONNECTION=vector_db
```

3. Run the migrations on the vector database:
```bash
php artisan migrate --database=vector_db
```

### Vector Extension

The package automatically creates the `vector` extension in PostgreSQL when using a separate connection. This extension is required for vector operations and is created by the `2022_08_03_000000_create_vector_extension` migration.

## Usage

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

### Finding Matching Results

```php
// Find jobs that match a customer's profile
$customer = Customer::find(1);
$matchingJobs = $customer->matchingResults(JobVerified::class, 10);

foreach ($matchingJobs as $job) {
    echo "Job: {$job->title} - Match: {$job->match_percent}%";
}
```

**Important**: The target model class (e.g., `JobVerified`) must implement `EmbeddingSearchableContract` to be searchable. If it doesn't, the method will throw an exception with a clear error message.

### Batch Processing

```php
use Subhendu\EmbedVector\Services\BatchEmbeddingService;

$batchService = app(BatchEmbeddingService::class);
$batch = $batchService->createBatch(['text1', 'text2', 'text3']);
```

## Commands

- `php artisan embedding:gen {model} {--type=sync|init} {--force}` - Generate batch embeddings for a specific model
- `php artisan embedding:proc {--batch-id=} {--all}` - Process completed batch results

### Command Options

#### `embedding:gen`
- `{model}` - The model class name to generate embeddings for
- `--type=sync` - Processing type (default: sync)
- `--force` - Force overwrite existing files

#### `embedding:proc`
- `--batch-id=` - Process a specific batch by ID
- `--all` - Process all completed batches
- No options - Check and process batches that are ready (default behavior)

### Usage Examples

```bash
# Generate embeddings for User model
php artisan embedding:gen User --type=sync --force

# Process a specific batch
php artisan embedding:proc --batch-id=123

# Process all completed batches
php artisan embedding:proc --all

# Check and process ready batches (default)
php artisan embedding:proc
```

## Configuration Options

- `openai_api_key`: Your OpenAI API key
- `embedding_model`: The embedding model to use (default: text-embedding-3-small)
- `distance_metric`: Distance metric for similarity (cosine or l2)
- `lot_size`: Maximum items per batch (OpenAI limit: 50,000)
- `chunk_size`: Items processed per chunk (default: 500)
- `database_connection`: Database connection for vector operations
- `model_fields_to_check`: Fields to monitor for syncing events

## Typical Flow

1. **Source Model** (Customer) generates embedding via `toEmbeddingText()`
2. **Target Model** (Job) stores embeddings and provides search capabilities
3. **Matching** occurs when source embeddings are used to find similar target models
4. **Results** include similarity scores and match percentages

### Contract Hierarchy

- **`EmbeddableContract`**: Base contract for models that generate embeddings AND can find matches
- **`EmbeddingSearchableContract`**: Extends `EmbeddableContract` for models that can also be searched/targeted

### Trait Hierarchy

- **`EmbeddableTrait`**: Provides embedding generation and matching functionality
- **`EmbeddingSearchableTrait`**: Extends `EmbeddableTrait` and adds query/sync capabilities for searchable models

## Support

For issues and questions, please check the package repository or create an issue.


