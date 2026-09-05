<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'target_format', 'description',
        'system_prompt', 'user_template', 'is_default', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function defaultFor(string $format = 'video'): ?self
    {
        return static::active()
            ->orderByRaw('case when target_format = ? then 0 else 1 end', [$format])
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->first();
    }
}
