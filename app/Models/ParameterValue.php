<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParameterValue extends Model
{
    protected $fillable = [
        'parameter_category_id', 'label', 'slug', 'code', 'product_id', 'position', 'is_archived',
    ];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ParameterCategory::class, 'parameter_category_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CreativeParameter::class);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }
}
