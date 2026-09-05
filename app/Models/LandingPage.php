<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPage extends Model
{
    protected $fillable = [
        'name', 'url', 'landing_page_type_id', 'product_id', 'version', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LandingPageType::class, 'landing_page_type_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }
}
