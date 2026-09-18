<?php

namespace App\Models;

use App\Scopes\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CmsPage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'title_ar',
        'slug',
        'body',
        'body_ar',
        'meta_title',
        'meta_description',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToStore);

        static::creating(function (CmsPage $page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function displayName(): string
    {
        $locale = app()->getLocale();

        return $locale === 'ar' && $this->title_ar ? $this->title_ar : $this->title;
    }

    public function displayBody(): string
    {
        $locale = app()->getLocale();

        return $locale === 'ar' && $this->body_ar ? $this->body_ar : $this->body;
    }
}
