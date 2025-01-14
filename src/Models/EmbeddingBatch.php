<?php

namespace Subhendu\Recommender\Models;

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
}
