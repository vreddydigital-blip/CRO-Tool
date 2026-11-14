<?php

namespace App\Services\Analysis;

/**
 * Transparent CRO health scoring.
 *
 * The score is a HEURISTIC WEBSITE ASSESSMENT. It is not a conversion rate, and
 * it does not predict conversion. It measures how many recognised CRO best
 * practices are present or absent in the crawled HTML.
 *
 * Category weights (sum = 100):
 *   value_proposition 20, cta 20, trust 15, ux 15, mobile 15, content 10, technical 5
 */
class ScoringEngine
{
    public const WEIGHTS = [
        'value_proposition' => 20,
        'cta' => 20,
        'trust' => 15,
        'ux' => 15,
        'mobile' => 15,
        'content' => 10,
        'technical' => 5,
    ];

    public const SEVERITY_PENALTY = [
        'critical' => 22,
        'high' => 14,
        'medium' => 8,
        'low' => 3,
    ];

    /**
     * @param array $findings  list of finding arrays (category, severity, rule_id)
     * @param int   $pagesAnalyzed
     * @return array{overall:float, categories:array, confidence:float, evidence_coverage:float, counts:array}
     */
    public function score(array $findings, int $pagesAnalyzed): array
    {
        $byCategory = [];
        foreach (array_keys(self::WEIGHTS) as $cat) {
            $byCategory[$cat] = [];
        }

        foreach ($findings as $f) {
            $cat = $f['category'] ?? 'ux';
            if (! isset($byCategory[$cat])) {
                $byCategory[$cat] = [];
            }
            $byCategory[$cat][] = $f;
        }

        $categories = [];
        $overall = 0.0;
        $totalWeight = array_sum(self::WEIGHTS);

        foreach (self::WEIGHTS as $cat => $weight) {
            $catFindings = $byCategory[$cat] ?? [];

            // Deduplicate by rule_id: a rule firing on 5 pages is one issue,
            // but repeated occurrences increase the penalty modestly (capped).
            $byRule = [];
            foreach ($catFindings as $f) {
                $rid = $f['rule_id'] ?? 'unknown';
                $byRule[$rid] = ($byRule[$rid] ?? 0) + 1;
            }

            $penalty = 0.0;
            foreach ($byRule as $rid => $occurrences) {
                $sev = 'medium';
                foreach ($catFindings as $f) {
                    if (($f['rule_id'] ?? '') === $rid) {
                        $sev = $f['severity'] ?? 'medium';
                        break;
                    }
                }
                $base = self::SEVERITY_PENALTY[$sev] ?? 8;
                $mult = $occurrences >= 3 ? 1.4 : ($occurrences === 2 ? 1.25 : 1.0);
                $penalty += $base * $mult;
            }

            $score = max(0, min(100, 100 - $penalty));

            $categories[$cat] = [
                'score' => round($score, 2),
                'weight' => $weight,
                'issues_count' => count($catFindings),
                'unique_rules' => count($byRule),
            ];

            $overall += $score * ($weight / $totalWeight);
        }

        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($findings as $f) {
            $sev = $f['severity'] ?? 'low';
            if (isset($counts[$sev])) {
                $counts[$sev]++;
            }
        }

        $evidenceCoverage = $pagesAnalyzed > 0
            ? min(100, round(($pagesAnalyzed / 3) * 100, 2))
            : 0;

        $confidence = $pagesAnalyzed >= 5 ? 85 : ($pagesAnalyzed >= 3 ? 70 : ($pagesAnalyzed >= 2 ? 55 : 35));

        return [
            'overall' => round($overall, 2),
            'categories' => $categories,
            'confidence' => (float) $confidence,
            'evidence_coverage' => (float) $evidenceCoverage,
            'counts' => $counts,
        ];
    }

    /** Human-readable methodology, shown in the UI and the PDF. */
    public static function methodology(): array
    {
        return [
            'title' => 'How the CRO Health Score is calculated',
            'disclaimer' => 'This is a heuristic assessment of publicly visible website elements. It is not a conversion rate, and it does not predict or guarantee conversion uplift.',
            'weights' => self::WEIGHTS,
            'penalties' => self::SEVERITY_PENALTY,
            'steps' => [
                'Each page is fetched and parsed. Rules inspect only elements actually present in the HTML.',
                'Each triggered rule deducts points from its category, scaled by severity and by how many pages it affects (capped at 1.4x).',
                'A category with no triggered rules scores 100.',
                'The overall score is the weighted average of the seven category scores.',
                'Confidence rises with the number of pages analysed; evidence coverage reflects how much of the site was inspected.',
            ],
            'not_measured' => [
                'Real visitor behaviour, traffic volume or conversion rate',
                'JavaScript-rendered content and client-side interactions',
                'Visual design quality, rendered contrast and actual tap-target sizes',
                'A/B test results or causal impact of any recommendation',
            ],
        ];
    }
}
