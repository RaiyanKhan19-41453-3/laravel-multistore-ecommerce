<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    protected $fillable = [
        'store_id',
        'key',
        'value',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'string',
        ];
    }
}
