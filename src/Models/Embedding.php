<?php

namespace Subhendu\Recommender\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Vector;

class Embedding extends Model
{
    use HasNeighbors;

    protected $casts = ['embedding' => Vector::class];

    protected $guarded = [];
}
