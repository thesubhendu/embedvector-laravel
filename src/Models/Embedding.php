<?php

namespace Subhendu\EmbedVector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class Embedding extends Model
{
    use HasNeighbors;

    protected $casts = [
        'embedding' => Vector::class,
    ];

    protected $guarded = [];

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName(): ?string
    {
        return config('embedvector.database_connection') ?: $this->connection;
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
