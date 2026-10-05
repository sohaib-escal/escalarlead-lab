<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadParameter extends Model
{
    protected $fillable = [
        'lead_id', 'parameter_category_id', 'parameter_value_id', 'confidence', 'source_message_id',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
