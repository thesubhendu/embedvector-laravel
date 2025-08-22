<?php

namespace Subhendu\EmbedVector\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Subhendu\EmbedVector\Contracts\EmbeddingSearchableContract;
use Subhendu\EmbedVector\Traits\EmbeddingSearchableTrait;

class Job extends Model implements EmbeddingSearchableContract
{
    use EmbeddingSearchableTrait;
    use HasFactory;

    public function toEmbeddingText(): string
    {
        return $this->department;
    }
}
