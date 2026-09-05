<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Aggregates raw metric rows into the KPIs the media team actually looks at.
 */
class MetricsSummary
{
    public function __construct(
        public float $spend = 0,
        public int $impressions = 0,
        public int $reach = 0,
        public int $clicks = 0,
        public int $leads = 0,
        public int $qualified_leads = 0,
        public int $contacted = 0,
        public int $phone_qualified = 0,
        public int $appointments = 0,
        public int $confirmed = 0,
        public int $sales = 0,
        public float $revenue = 0,
    ) {}

    public static function fromRows(Collection $rows): self
    {
        return new self(
            spend: (float) $rows->sum('spend'),
            impressions: (int) $rows->sum('impressions'),
            reach: (int) $rows->sum('reach'),
            clicks: (int) $rows->sum('clicks'),
            leads: (int) $rows->sum('leads'),
            qualified_leads: (int) $rows->sum('qualified_leads'),
            contacted: (int) $rows->sum('contacted'),
            phone_qualified: (int) $rows->sum('phone_qualified'),
            appointments: (int) $rows->sum('appointments'),
            confirmed: (int) $rows->sum('confirmed'),
            sales: (int) $rows->sum('sales'),
            revenue: (float) $rows->sum('revenue'),
        );
    }

    /**
     * Build a summary from `withSum('metrics', ...)` aggregates on a model.
     */
    public static function fromAggregates(object $model, string $prefix = 'metrics_sum_'): self
    {
        $get = fn (string $column) => $model->{$prefix.$column} ?? 0;

        return new self(
            spend: (float) $get('spend'),
            impressions: (int) $get('impressions'),
            reach: (int) $get('reach'),
            clicks: (int) $get('clicks'),
            leads: (int) $get('leads'),
            qualified_leads: (int) $get('qualified_leads'),
            contacted: (int) $get('contacted'),
            phone_qualified: (int) $get('phone_qualified'),
            appointments: (int) $get('appointments'),
            confirmed: (int) $get('confirmed'),
            sales: (int) $get('sales'),
            revenue: (float) $get('revenue'),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function columns(): array
    {
        return [
            'spend', 'impressions', 'reach', 'clicks', 'leads', 'qualified_leads',
            'contacted', 'phone_qualified', 'appointments', 'confirmed', 'sales', 'revenue',
        ];
    }

    public function plus(self $other): self
    {
        return new self(
            spend: $this->spend + $other->spend,
            impressions: $this->impressions + $other->impressions,
            reach: $this->reach + $other->reach,
            clicks: $this->clicks + $other->clicks,
            leads: $this->leads + $other->leads,
            qualified_leads: $this->qualified_leads + $other->qualified_leads,
            contacted: $this->contacted + $other->contacted,
            phone_qualified: $this->phone_qualified + $other->phone_qualified,
            appointments: $this->appointments + $other->appointments,
            confirmed: $this->confirmed + $other->confirmed,
            sales: $this->sales + $other->sales,
            revenue: $this->revenue + $other->revenue,
        );
    }

    private static function ratio(float $numerator, float $denominator, int $precision = 2): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator, $precision) : null;
    }

    public function ctr(): ?float
    {
        return self::ratio($this->clicks * 100, $this->impressions);
    }

    public function cpc(): ?float
    {
        return self::ratio($this->spend, $this->clicks);
    }

    public function cpm(): ?float
    {
        return self::ratio($this->spend * 1000, $this->impressions);
    }

    public function cpl(): ?float
    {
        return self::ratio($this->spend, $this->leads);
    }

    public function costPerQualified(): ?float
    {
        return self::ratio($this->spend, $this->qualified_leads);
    }

    public function costPerAppointment(): ?float
    {
        return self::ratio($this->spend, $this->appointments);
    }

    public function costPerConfirmed(): ?float
    {
        return self::ratio($this->spend, $this->confirmed);
    }

    public function costPerSale(): ?float
    {
        return self::ratio($this->spend, $this->sales);
    }

    public function roas(): ?float
    {
        return self::ratio($this->revenue, $this->spend);
    }

    public function qualificationRate(): ?float
    {
        return self::ratio($this->qualified_leads * 100, $this->leads);
    }

    /**
     * Automatic rating: winner | promising | average | poor | testing | no_data.
     */
    public function rating(): string
    {
        if ($this->spend <= 0 && $this->leads === 0) {
            return 'no_data';
        }

        if ($this->spend < config('creative.rating_min_spend')) {
            return 'testing';
        }

        $cpq = $this->costPerQualified();

        if ($cpq === null) {
            return 'poor';
        }

        $t = config('creative.rating_thresholds');

        return match (true) {
            $cpq <= $t['winner'] => 'winner',
            $cpq <= $t['promising'] => 'promising',
            $cpq <= $t['average'] => 'average',
            default => 'poor',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'spend' => round($this->spend, 2),
            'impressions' => $this->impressions,
            'reach' => $this->reach,
            'clicks' => $this->clicks,
            'leads' => $this->leads,
            'qualified_leads' => $this->qualified_leads,
            'contacted' => $this->contacted,
            'phone_qualified' => $this->phone_qualified,
            'appointments' => $this->appointments,
            'confirmed' => $this->confirmed,
            'sales' => $this->sales,
            'revenue' => round($this->revenue, 2),
            'ctr' => $this->ctr(),
            'cpc' => $this->cpc(),
            'cpm' => $this->cpm(),
            'cpl' => $this->cpl(),
            'cost_per_qualified' => $this->costPerQualified(),
            'cost_per_appointment' => $this->costPerAppointment(),
            'cost_per_confirmed' => $this->costPerConfirmed(),
            'cost_per_sale' => $this->costPerSale(),
            'roas' => $this->roas(),
            'qualification_rate' => $this->qualificationRate(),
            'rating' => $this->rating(),
        ];
    }
}
