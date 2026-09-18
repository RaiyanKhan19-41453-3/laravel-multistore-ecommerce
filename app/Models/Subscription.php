<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'store_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'current_period_started_at',
        'current_period_ends_at',
        'gateway',
        'gateway_subscription_id',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_started_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isEntitled(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true)
            && ! $this->isPeriodExpired();
    }

    public function isPeriodExpired(): bool
    {
        $end = $this->status === self::STATUS_TRIALING
            ? $this->trial_ends_at
            : $this->current_period_ends_at;

        return $end !== null && $end->isPast();
    }
}
