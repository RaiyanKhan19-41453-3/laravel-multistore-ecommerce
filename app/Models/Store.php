<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'country',
        'currency',
        'locale',
        'timezone',
        'is_active',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->slug) && ! empty($store->name)) {
                $store->slug = Str::slug($store->name);
            }

            if (empty($store->currency)) {
                $store->currency = 'BDT';
            }

            if (empty($store->locale)) {
                $store->locale = 'en';
            }

            if (empty($store->timezone)) {
                $store->timezone = 'Asia/Dhaka';
            }

            if (empty($store->country)) {
                $store->country = 'BD';
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Platform billing state: trialing, active, pending, past_due,
     * canceled, or expired. Stores without a subscription row trial
     * from their creation date (platform.billing.trial_days).
     */
    public function billingStatus(): string
    {
        try {
            $sub = $this->subscription;

            if ($sub) {
                if ($sub->isEntitled()) {
                    return $sub->status;
                }

                return in_array($sub->status, [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE], true)
                    ? 'expired'
                    : $sub->status;
            }

            $trialDays = (int) config('platform.billing.trial_days', 14);
            $trialEnds = $this->created_at?->copy()->addDays($trialDays);

            if ($trialEnds === null || $trialEnds->isFuture()) {
                return Subscription::STATUS_TRIALING;
            }

            return 'expired';
        } catch (\Throwable) {
            // Billing must never break storefront or admin rendering.
            return Subscription::STATUS_ACTIVE;
        }
    }

    public function isBillingEntitled(): bool
    {
        return in_array($this->billingStatus(), [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE], true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        try {
            return self::query()->active()->orderBy('id')->first()
                ?? self::query()->orderBy('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
