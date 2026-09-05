<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'guest_email',
        'guest_phone',
        'order_number',
        'status',
        'subtotal',
        'discount_total',
        'shipping_cost',
        'tax_amount',
        'total',
        'coupon_id',
        'coupon_code',
        'shipping_method_id',
        'shipping_method_name',
        'shipping_estimated_days',
        'fulfillment_type',
        'discount_ids',
        'notes',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'cancellation_reason',
        'delivered_at',
        'shipped_at',
        'cancelled_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'discount_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function appliedDiscounts()
    {
        if (empty($this->discount_ids)) {
            return collect();
        }

        return Discount::whereIn('id', $this->discount_ids)->get();
    }

    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest()->first();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '!=', null)
            ->where('expires_at', '<=', now());
    }

    private static function generateOrderNumber(): string
    {
        $maxRetries = 5;

        for ($i = 0; $i < $maxRetries; $i++) {
            $orderNumber = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

            if (! self::where('order_number', $orderNumber)->exists()) {
                return $orderNumber;
            }
        }

        return 'ORD-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
    }
}
