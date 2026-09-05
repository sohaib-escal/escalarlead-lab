<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPageType extends Model
{
    protected $fillable = ['name', 'slug', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class);
    }
}
