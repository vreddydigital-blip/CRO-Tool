<?php

namespace Tests\Unit;

use App\Services\Analysis\Priority;
use PHPUnit\Framework\TestCase;

class PriorityTest extends TestCase
{
    public function test_formula_is_impact_times_confidence_times_reach_over_effort(): void
    {
        // (5 * 4 * 3) / 2 = 30
        $this->assertSame(30.0, Priority::score(5, 4, 3, 2));
    }

    public function test_effort_is_never_zero(): void
    {
        $this->assertGreaterThan(0, Priority::score(5, 5, 5, 0));
    }

    public function test_labels_are_ordered(): void
    {
        $this->assertSame('critical', Priority::label(60));
        $this->assertSame('high', Priority::label(30));
        $this->assertSame('medium', Priority::label(15));
        $this->assertSame('low', Priority::label(3));
    }
}
