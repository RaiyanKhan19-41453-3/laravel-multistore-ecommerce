<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'path',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'sort_order',
        'is_primary',
        'paths',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'paths' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ProductImage $image) {
            $image->deleteFiles();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeProductLevel($query)
    {
        return $query->whereNull('product_variant_id');
    }

    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('product_variant_id', $variantId);
    }

    public function getUrl(string $size = 'original'): string
    {
        $path = $this->paths[$size] ?? $this->path;

        return '/storage/'.$path;
    }

    public function getUrls(): array
    {
        $urls = [];
        foreach ($this->paths as $size => $path) {
            $urls[$size] = '/storage/'.$path;
        }

        return $urls;
    }

    private function deleteFiles(): void
    {
        foreach ($this->paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
