<?php

namespace App\Models;

use App\Support\MetricsSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeMetric extends Model
{
    protected $fillable = [
        'creative_id', 'campaign_id', 'channel_id', 'period_start', 'period_end',
        'spend', 'impressions', 'reach', 'clicks',
        'leads', 'qualified_leads', 'contacted', 'phone_qualified',
        'appointments', 'confirmed', 'sales', 'revenue', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'spend' => 'float',
            'revenue' => 'float',
        ];
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function summary(): MetricsSummary
    {
        return MetricsSummary::fromRows(collect([$this]));
    }
}
