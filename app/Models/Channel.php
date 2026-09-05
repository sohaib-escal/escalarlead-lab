<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Channel extends Model
{
    protected $fillable = [
        'name', 'slug', 'code', 'default_utm_source', 'default_utm_medium', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creatives(): BelongsToMany
    {
        return $this->belongsToMany(Creative::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
