<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'courier_id',
        'courier',
        'tracking_number',
        'courier_order_id',
        'status',
        'shipping_cost',
        'note',
        'raw_response',
        'courier_response',
    ];

    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:2',
            'raw_response' => 'array',
            'courier_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courierRelation(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'picked' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'failed' => 'Delivery Failed',
            'returned' => 'Returned',
            default => ucfirst($this->status),
        };
    }
}
