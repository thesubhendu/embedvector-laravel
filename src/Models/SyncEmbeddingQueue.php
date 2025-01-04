<?php

namespace Subhendu\Recommender\Models;

use Illuminate\Database\Eloquent\Model;

class SyncEmbeddingQueue extends Model
{
    protected $connection = 'pgsql';

    protected $guarded = [];

    protected $table = 'sync_embedding_queues';

    public static function pushToQueue(Model $model)
    {
        return self::query()->firstOrCreate(['model_id' => $model->getKey(), 'model_type' => get_class($model)]);
    }
}
