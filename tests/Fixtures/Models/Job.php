<?php

namespace Subhendu\Recommender\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Subhendu\Recommender\Contracts\EmbeddableContract;
use Subhendu\Recommender\Traits\EmbeddableTrait;

class Job extends Model implements EmbeddableContract
{
    use EmbeddableTrait;
    use HasFactory;


    public function toEmbeddingText(): string
    {
        return $this->department;
    }
}
