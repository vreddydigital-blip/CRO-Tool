<?php

namespace App\Services\Ai;

/**
 * Deterministic, no-API explanation layer.
 *
 * This is the default. It composes plain-language guidance from the rule
 * metadata we already generated — it never invents evidence.
 */
class RuleBasedFallback implements AiProvider
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function explain(array $findings, string $pageUrl): array
    {
        if ($findings === []) {
            return ['ok' => true, 'text' => 'No issues were detected on this page by the rule engine.', 'provider' => 'rule_based'];
        }

        $lines = [];
        $lines[] = 'Summary for '.$pageUrl;
        $lines[] = '';

        $bySeverity = ['critical' => [], 'high' => [], 'medium' => [], 'low' => []];
        foreach ($findings as $f) {
            $bySeverity[$f['severity'] ?? 'low'][] = $f;
        }

        foreach ($bySeverity as $sev => $items) {
            if ($items === []) {
                continue;
            }
            $lines[] = strtoupper($sev).' ('.count($items).')';
            foreach (array_slice($items, 0, 5) as $f) {
                $lines[] = '- '.$f['rule_name'].': '.$f['recommended_action'];
            }
            $lines[] = '';
        }

        $lines[] = 'These are rule-based observations of the page HTML. They are not a conversion prediction.';

        return ['ok' => true, 'text' => implode("\n", $lines), 'provider' => 'rule_based'];
    }
}
