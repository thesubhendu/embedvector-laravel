<?php

namespace Subhendu\EmbedVector\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Traits\EmbeddableTrait;

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
