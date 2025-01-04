<?php

namespace Subhendu\Recommender\Traits;

use Illuminate\Database\Eloquent\Model;
use Subhendu\Recommender\Models\SyncEmbeddingQueue;

trait FireSyncEmbeddingTrait
{
    public static function bootFireSyncEmbeddingsTrait()
    {
        static::saved(function (Model $model) {
            if (static::canFireEvent($model)) {
                SyncEmbeddingQueue::pushToQueue($model);
            }
        });
    }

    private static function getFieldsToCheck(Model $model): array
    {
        $map = config('recommender.model_fields_to_check');

        return $map[get_class($model)] ?? [];
    }

    private static function canFireEvent(Model $model): bool
    {
        $fields = static::getFieldsToCheck($model);

        if (empty($fields)) {
            return false;
        }

        foreach ($fields as $field) {
            if ($model->getOriginal($field) !== $model->{$field}) {
                return true;
            }
        }

        return false;
    }
}
