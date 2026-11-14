<?php

namespace App\Services\Analysis;

/**
 * Opportunity prioritisation.
 *
 * Priority = (Impact × Confidence × Reach) ÷ Effort
 *
 * Every factor is on a 1–5 scale. Effort is inverted by division, so a
 * high-effort fix ranks lower than an equally impactful cheap one.
 * The result is a relative ordering of work, not a predicted uplift.
 */
class Priority
{
    public const CRITICAL = 40;
    public const HIGH = 25;
    public const MEDIUM = 12;

    public static function score(int $impact, int $confidence, int $reach, int $effort): float
    {
        $effort = max(1, $effort);

        return round(($impact * $confidence * $reach) / $effort, 2);
    }

    public static function label(float $score): string
    {
        return match (true) {
            $score >= self::CRITICAL => 'critical',
            $score >= self::HIGH => 'high',
            $score >= self::MEDIUM => 'medium',
            default => 'low',
        };
    }
}
