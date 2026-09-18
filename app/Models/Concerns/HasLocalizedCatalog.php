<?php

namespace App\Models\Concerns;

trait HasLocalizedCatalog
{
    public function displayName(): string
    {
        if (app()->getLocale() === 'ar' && filled($this->getAttribute('name_ar'))) {
            return (string) $this->getAttribute('name_ar');
        }

        return (string) $this->getAttribute('name');
    }

    public function displayDescription(): ?string
    {
        if (app()->getLocale() === 'ar' && filled($this->getAttribute('description_ar'))) {
            return $this->getAttribute('description_ar');
        }

        return $this->getAttribute('description');
    }
}
