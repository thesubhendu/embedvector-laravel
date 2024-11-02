<?php

namespace Subhendu\Recommender\Commands;

use Illuminate\Console\Command;

class RecommenderCommand extends Command
{
    public $signature = 'recommender';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
