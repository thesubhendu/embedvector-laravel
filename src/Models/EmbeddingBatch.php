<?php

namespace Subhendu\EmbedVector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $batch_id
 * @property string $status
 * @property string $saved_file_path
 * @property string $output_file_id
 * @property string $embeddable_model
 */
class EmbeddingBatch extends Model
{
    protected $guarded = [];

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName(): ?string
    {
        return config('embedvector.database_connection') ?: $this->connection;
    }
}
