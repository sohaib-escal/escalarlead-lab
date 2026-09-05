<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreativeStatus extends Model
{
    protected $fillable = [
        'name', 'slug', 'color', 'counts_as_live', 'is_archived_state', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'counts_as_live' => 'boolean',
            'is_archived_state' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }
}
