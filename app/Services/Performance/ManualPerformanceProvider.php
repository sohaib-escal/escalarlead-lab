<?php

namespace App\Services\Performance;

use App\Models\Creative;
use App\Support\MetricsSummary;

class ManualPerformanceProvider implements PerformanceProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return config('integrations.performance.manual.label');
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function totalsFor(Creative $creative): MetricsSummary
    {
        return $creative->summary();
    }
}
