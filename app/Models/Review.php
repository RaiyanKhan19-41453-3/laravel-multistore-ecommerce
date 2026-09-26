<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'title',
        'body',
        'is_approved',
        'verified_purchase',
        'guest_name',
        'guest_email',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
            'verified_purchase' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true)->where('verified_purchase', true);
    }
}
