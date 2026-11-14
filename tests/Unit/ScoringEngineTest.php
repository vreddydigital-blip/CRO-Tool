<?php

namespace Tests\Unit;

use App\Services\Analysis\ScoringEngine;
use PHPUnit\Framework\TestCase;

class ScoringEngineTest extends TestCase
{
    public function test_weights_sum_to_one_hundred(): void
    {
        $this->assertSame(100, array_sum(ScoringEngine::WEIGHTS));
    }

    public function test_all_seven_categories_are_weighted(): void
    {
        $this->assertCount(7, ScoringEngine::WEIGHTS);
        foreach (['value_proposition','cta','trust','ux','mobile','content','technical'] as $cat) {
            $this->assertArrayHasKey($cat, ScoringEngine::WEIGHTS);
        }
    }

    public function test_a_clean_site_scores_high(): void
    {
        $engine = new ScoringEngine();
        $result = $engine->score([], 10, 10);
        $this->assertGreaterThanOrEqual(90, $result['overall']);
    }

    public function test_critical_findings_lower_the_score(): void
    {
        $engine = new ScoringEngine();
        $clean = $engine->score([], 10, 10);
        $dirty = $engine->score([
            ['category' => 'cta', 'severity' => 'critical'],
            ['category' => 'trust', 'severity' => 'critical'],
            ['category' => 'value_proposition', 'severity' => 'high'],
        ], 10, 10);
        $this->assertLessThan($clean['overall'], $dirty['overall']);
    }

    public function test_score_is_bounded_between_zero_and_hundred(): void
    {
        $engine = new ScoringEngine();
        $findings = [];
        for ($i = 0; $i < 200; $i++) {
            $findings[] = ['category' => 'cta', 'severity' => 'critical'];
        }
        $result = $engine->score($findings, 1, 1);
        $this->assertGreaterThanOrEqual(0, $result['overall']);
        $this->assertLessThanOrEqual(100, $result['overall']);
    }

    public function test_confidence_reflects_pages_analysed(): void
    {
        $engine = new ScoringEngine();
        $one = $engine->score([], 1, 1);
        $ten = $engine->score([], 10, 10);
        $this->assertLessThan($ten['confidence'], $one['confidence']);
    }
}
