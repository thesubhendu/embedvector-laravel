# EmbedVector Laravel Package

A Laravel package for handling OpenAI embeddings with batch processing capabilities.

## Features

- Batch embedding processing using OpenAI's batch API
- Separate database connection support for vector operations
- Automatic vector extension creation for PostgreSQL
- Efficient batch processing with configurable chunk sizes

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

### Basic Embedding

```php
use Subhendu\EmbedVector\Services\EmbeddingService;

$embeddingService = app(EmbeddingService::class);
$embedding = $embeddingService->createEmbedding('Your text here');
```

### Batch Processing

```php
use Subhendu\EmbedVector\Services\BatchEmbeddingService;

$batchService = app(BatchEmbeddingService::class);
$batch = $batchService->createBatch(['text1', 'text2', 'text3']);
```

## Commands

- `php artisan embedvector:batch` - Process batch embeddings
- `php artisan embedvector:process-completed` - Process completed batch results

## Configuration Options

- `openai_api_key`: Your OpenAI API key
- `embedding_model`: The embedding model to use (default: text-embedding-3-small)
- `distance_metric`: Distance metric for similarity (cosine or l2)
- `lot_size`: Maximum items per batch (OpenAI limit: 50,000)
- `chunk_size`: Items processed per chunk (default: 500)
- `database_connection`: Database connection for vector operations
- `model_fields_to_check`: Fields to monitor for syncing events

## Support

For issues and questions, please check the package repository or create an issue.


