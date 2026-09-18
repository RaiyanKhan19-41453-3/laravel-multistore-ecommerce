<?php

namespace App\Scopes;

use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToStore implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = app(CurrentStore::class)->explicitlySetId();

        if ($storeId !== null) {
            $builder->where($model->getTable().'.store_id', $storeId);
        }
    }
}
