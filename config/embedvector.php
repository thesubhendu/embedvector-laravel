<?php

return [
    'openai_api_key' => env('OPENAI_API_KEY', 'lm-studio'),
    'api_base_uri' => env('OPENAI_BASE_URI', ''),
    'embedding_model'=> env('EMBEDDING_MODEL', 'text-embedding-3-small'),
    'lot_size' => env('EMBEDVECTOR_LOT_SIZE', 50000), // openai limit of how many items processing/batch
    'chunk_size' => env('EMBEDVECTOR_CHUNK_SIZE', 500), // processes in 500 model chunk
    'directories' => [
        'input' => 'embeddings/input',
        'output' => 'embeddings/output',
    ],
    // Optionally add fields to check for syncing events:
    'model_fields_to_check' => [
        // Fully qualify your model classes and their fields here
        // \App\Models\YourModel::class => ['field1', 'field2'],
    ],
];
