<?php

namespace Subhendu\EmbedVector\Traits;

use Illuminate\Database\Eloquent\Model;
use Subhendu\EmbedVector\Models\Embedding;

trait FireSyncEmbeddingTrait
{
    public static function bootFireSyncEmbeddingTrait()
    {
        static::saved(function (Model $model) {
            if (static::canFireEvent($model)) {
                Embedding::where('model_id', $model->getKey())
                    ->where('model_type', get_class($model))
                    ->update(['embedding_sync_required' => true]);
            }
        });
    }

    private static function getFieldsToCheck(Model $model): array
    {
        $map = config('embedvector.model_fields_to_check');

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
