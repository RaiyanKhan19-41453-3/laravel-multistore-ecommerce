<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    public const LAYOUTS = ['single', 'double', 'quad'];

    public const TEXT_LAYOUTS = ['split', 'left', 'center'];

    public const MEDIA_TYPES = ['image', 'iframe'];

    protected $fillable = [
        'store_id',
        'title',
        'title_ar',
        'subtitle',
        'subtitle_ar',
        'button_label',
        'button_label_ar',
        'button_link',
        'layout',
        'text_layout',
        'show_title',
        'show_subtitle',
        'show_button',
        'media_type',
        'iframe_url',
        'images',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_active' => 'boolean',
            'show_title' => 'boolean',
            'show_subtitle' => 'boolean',
            'show_button' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    private function localized(?string $ar, ?string $en): string
    {
        if (app()->getLocale() === 'ar' && filled($ar)) {
            return (string) $ar;
        }

        return (string) ($en ?? '');
    }

    public function displayTitle(): string
    {
        return $this->localized($this->title_ar, $this->title);
    }

    public function displaySubtitle(): string
    {
        return $this->localized($this->subtitle_ar, $this->subtitle);
    }

    public function displayButtonLabel(): string
    {
        return $this->localized($this->button_label_ar, $this->button_label);
    }

    /**
     * @return array<int, string>
     */
    public function imageList(): array
    {
        return array_values(array_filter(is_array($this->images) ? $this->images : []));
    }
}
