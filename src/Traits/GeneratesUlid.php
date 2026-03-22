<?php

namespace ScriptDevelop\MetaCatalogManager\Traits;

use Illuminate\Support\Str;

trait GeneratesUlid
{
    protected static function bootGeneratesUlid(): void
    {
        static::creating(function ($model) {
            $keyName = $model->getKeyName();
            if (empty($model->{$keyName})) {
                $model->{$keyName} = Str::ulid()->toString();
            }
        });
    }
}
