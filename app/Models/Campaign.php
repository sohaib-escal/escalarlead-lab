<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    public const STATUSES = ['draft', 'active', 'paused', 'completed', 'archived'];

    protected $fillable = [
        'name', 'code', 'product_id', 'country', 'objective',
        'start_date', 'end_date', 'budget', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class);
    }

    public function creatives(): BelongsToMany
    {
        return $this->belongsToMany(Creative::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(CreativeMetric::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
