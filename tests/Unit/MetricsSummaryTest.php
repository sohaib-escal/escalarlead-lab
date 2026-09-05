<?php

namespace Tests\Unit;

use App\Support\MetricsSummary;
use Tests\TestCase;

class MetricsSummaryTest extends TestCase
{
    public function test_it_derives_the_costs_the_media_team_looks_at(): void
    {
        $summary = new MetricsSummary(
            spend: 420,
            impressions: 96000,
            clicks: 2100,
            leads: 84,
            qualified_leads: 42,
            appointments: 21,
            confirmed: 12,
            sales: 4,
            revenue: 19600,
        );

        $this->assertSame(5.0, $summary->cpl());
        $this->assertSame(10.0, $summary->costPerQualified());
        $this->assertSame(20.0, $summary->costPerAppointment());
        $this->assertSame(35.0, $summary->costPerConfirmed());
        $this->assertSame(105.0, $summary->costPerSale());
        $this->assertSame(46.67, $summary->roas());
        $this->assertSame(2.19, $summary->ctr());
    }

    public function test_rating_is_driven_by_qualified_lead_cost_not_raw_cpl(): void
    {
        // Cheap leads that never qualify are not good leads.
        $cheapButUseless = new MetricsSummary(spend: 300, leads: 150, qualified_leads: 5);
        $this->assertSame('poor', $cheapButUseless->rating());

        $winner = new MetricsSummary(spend: 400, leads: 60, qualified_leads: 40);
        $this->assertSame('winner', $winner->rating());

        $promising = new MetricsSummary(spend: 400, leads: 60, qualified_leads: 25);
        $this->assertSame('promising', $promising->rating());
    }

    public function test_small_spend_stays_in_test_and_empty_rows_report_no_data(): void
    {
        $this->assertSame('testing', (new MetricsSummary(spend: 40, leads: 5, qualified_leads: 1))->rating());
        $this->assertSame('no_data', (new MetricsSummary)->rating());
    }
}
