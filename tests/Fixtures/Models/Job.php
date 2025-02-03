<?php

namespace Subhendu\EmbedVector\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Subhendu\EmbedVector\Contracts\EmbeddableContract;
use Subhendu\EmbedVector\Traits\EmbeddableTrait;

class Job extends Model implements EmbeddableContract
{
    use EmbeddableTrait;
    use HasFactory;

    public function toEmbeddingText(): string
    {
        return $this->department;
    }
}
