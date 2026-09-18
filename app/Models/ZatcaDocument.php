<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaDocument extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    protected $fillable = [
        'store_id',
        'order_id',
        'device_serial',
        'type',
        'uuid',
        'icv',
        'invoice_number',
        'previous_invoice_hash',
        'invoice_hash',
        'xml',
        'qr_payload',
        'status',
        'gateway_response',
        'submit_attempts',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['reported', 'cleared'], true);
    }
}
