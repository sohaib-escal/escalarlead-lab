<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    protected $fillable = ['name', 'provider', 'model_id', 'notes', 'is_default', 'is_active', 'position'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(CreativePrompt::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        return static::active()->orderByDesc('is_default')->orderBy('position')->first();
    }
}
