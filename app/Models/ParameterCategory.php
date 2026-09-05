<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParameterCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'group', 'description', 'is_multi', 'in_tree', 'in_naming', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_multi' => 'boolean',
            'in_tree' => 'boolean',
            'in_naming' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(ParameterValue::class)->orderBy('position')->orderBy('label');
    }

    public function activeValues(): HasMany
    {
        return $this->values()->where('is_archived', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('name');
    }
}
