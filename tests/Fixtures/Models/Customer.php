<?php

namespace Subhendu\Recommender\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Subhendu\Recommender\Contracts\EmbeddableContract;
use Subhendu\Recommender\Traits\EmbeddableTrait;

class Customer extends Model implements EmbeddableContract
{
    use EmbeddableTrait;
    use HasFactory;

    protected $guarded = [];

    public function toEmbeddingText(): string
    {
        return $this->department;
    }
}
