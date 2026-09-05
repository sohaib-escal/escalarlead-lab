<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeParameter extends Model
{
    protected $fillable = ['creative_id', 'parameter_category_id', 'parameter_value_id'];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ParameterCategory::class, 'parameter_category_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ParameterValue::class, 'parameter_value_id');
    }
}
