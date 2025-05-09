<?php

namespace Subhendu\EmbedVector\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class Embedding extends Model
{
    use HasNeighbors;

    protected $casts = [
        'embedding' => Vector::class,
    ];

    protected $guarded = [];
}
