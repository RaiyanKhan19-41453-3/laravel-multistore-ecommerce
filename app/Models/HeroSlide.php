<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use HasFactory;

    public const LAYOUTS = ['split', 'full'];

    protected $fillable = [
        'store_id',
        'eyebrow',
        'eyebrow_ar',
        'title',
        'title_ar',
        'subtitle',
        'subtitle_ar',
        'cta_label',
        'cta_label_ar',
        'cta_link',
        'image',
        'layout',
        'show_eyebrow',
        'show_title',
        'show_subtitle',
        'show_button',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_eyebrow' => 'boolean',
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

    public function displayEyebrow(): string
    {
        return $this->localized($this->eyebrow_ar, $this->eyebrow);
    }

    public function displayTitle(): string
    {
        return $this->localized($this->title_ar, $this->title);
    }

    public function displaySubtitle(): string
    {
        return $this->localized($this->subtitle_ar, $this->subtitle);
    }

    public function displayCtaLabel(): string
    {
        return $this->localized($this->cta_label_ar, $this->cta_label);
    }
}
