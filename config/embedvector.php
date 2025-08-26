<?php

return [
    'openai_api_key' => env('OPENAI_API_KEY', ''),
    'embedding_model' => env('EMBEDVECTOR_MODEL', 'text-embedding-3-small'),
    // cosine | l2
    'distance_metric' => env('EMBEDVECTOR_DISTANCE', 'cosine'),
    
    // Search strategy: auto | optimized | cross_connection
    // auto: Automatically detect and choose optimal strategy (recommended)
    // optimized: Always use JOIN-based query (requires same DB connection)
    // cross_connection: Always use two-step approach (works across different DBs)
    'search_strategy' => env('EMBEDVECTOR_SEARCH_STRATEGY', 'auto'),
    'lot_size' => env('EMBEDVECTOR_LOT_SIZE', 50000), // openai limit of how many items processing/batch
    'chunk_size' => env('EMBEDVECTOR_CHUNK_SIZE', 500), // processes in 500 model chunk
    'directories' => [
        'input' => 'embeddings/input',
        'output' => 'embeddings/output',
    ],
    // Database connection to use for storing embeddings
    // Set to null to use the default application connection
    // Useful for separating embeddings into a different PostgreSQL database
    'database_connection' => env('EMBEDVECTOR_DB_CONNECTION', 'pgsql'),
    // Optionally add fields to check for syncing events:
    'model_fields_to_check' => [
        // Fully qualify your model classes and their fields here
        // \App\Models\YourModel::class => ['field1', 'field2'],
    ],
];
