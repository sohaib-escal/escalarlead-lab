<?php

namespace App\Services\Performance;

use App\Models\Creative;
use App\Support\MetricsSummary;

/**
 * Where a creative's numbers come from. Manual entry is the only implemented
 * source; the interface exists so an ad-platform source can be added without
 * touching the screens that read performance.
 */
interface PerformanceProvider
{
    public function key(): string;

    public function label(): string;

    /** False for sources that are declared but not implemented yet. */
    public function isAvailable(): bool;

    public function totalsFor(Creative $creative): MetricsSummary;
}
