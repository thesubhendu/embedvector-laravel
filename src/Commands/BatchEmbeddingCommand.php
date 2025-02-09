<?php

namespace Subhendu\EmbedVector\Commands;

use Illuminate\Console\Command;
use Subhendu\EmbedVector\Contracts\EmbeddingServiceContract;

class BatchEmbeddingCommand extends Command
{
    protected $signature = 'embedding:batch {modelName} {--type=sync}';

    protected $description = 'Generate a JSONL file for batch embedding';

    /**
     * Execute the console command.
     */
    public function handle(EmbeddingServiceContract $embeddingService): void
    {
        $modelClass = $this->argument('modelName');
        $type = $this->option('type'); // sync or init
        $result = $embeddingService->process($modelClass, $type);

        foreach ($result['messages'] as $message) {
            if ($result['success']) {
                $this->info($message);
            } else {
                $this->error($message);
            }
        }
    }
}
