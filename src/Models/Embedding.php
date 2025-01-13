<?php

namespace Subhendu\Recommender\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;

class Embedding extends Model
{
    use HasNeighbors;

    protected $guarded = [];
}
