<?php

namespace Subhendu\Recommender\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Subhendu\Recommender\Recommender
 */
class Recommender extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Subhendu\Recommender\Recommender::class;
    }
}
