<?php

namespace App\Services\Analysis;

use App\Models\Finding;
use App\Models\Page;
use App\Models\Recommendation;
use App\Models\Scan;
use App\Models\ScanLog;
use App\Models\Score;
use App\Services\Crawler\SiteCrawler;
use App\Services\Security\SafeHttpClient;
use App\Services\Security\UrlValidator;

/**
 * Runs one complete scan: crawl -> analyse -> score -> prioritise -> persist.
 *
 * Synchronous by design. EthioDeploy's free plan has no persistent worker and
 * sleeps after 30 minutes idle, so a queued job would never run reliably.
 * The crawl is bounded (10 pages, 45 s budget) to fit inside a web request.
 */
class ScanRunner
{
    public function __construct(
        private UrlValidator $validator,
        private SafeHttpClient $http,
        private SiteCrawler $crawler,
        private ScoringEngine $scoring,
    ) {}

    public function run(Scan $scan): Scan
    {
        $scan->update([
            'status' => 'running',
            'started_at' => now(),
            'progress' => 5,
            'error_message' => null,
        ]);

        $this->log($scan, 'info', 'Scan started for '.$scan->url);

        try {
            $crawl = $this->crawler->crawl($scan, $scan->url);

            if (! ($crawl['ok'] ?? false)) {
                $scan->update([
                    'status' => 'failed',
                    'error_message' => $crawl['error'] ?? 'Crawl failed.',
                    'completed_at' => now(),
                    'progress' => 100,
                ]);
                $this->log($scan, 'error', 'Crawl failed: '.($crawl['error'] ?? 'unknown'));
                return $scan->fresh();
            }

            $scan->update(['progress' => 45, 'pages_crawled' => $crawl['pages'], 'pages_failed' => $crawl['failed']]);

            $pages = $scan->pages()->whereNull('error')->get();
            $this->log($scan, 'info', 'Analysing '.$pages->count().' pages.');

            $allFindings = [];
            $rules = RuleLibrary::all();

            foreach ($pages as $page) {
                $html = $this->fetchHtmlForAnalysis($page);
                if ($html === null) {
                    $this->log($scan, 'warn', 'Could not re-read HTML for '.$page->url);
                    continue;
                }

                $ctx = new PageContext($html, $page->url, $page->status_code ?? 200, $page->load_time_ms);

                foreach ($rules as $rule) {
                    try {
                        $result = $rule->evaluate($ctx);
                    } catch (\Throwable $e) {
                        $this->log($scan, 'warn', "Rule {$rule->id()} failed on {$page->url}: ".$e->getMessage());
                        continue;
                    }

                    if ($result === null) {
                        continue;
                    }

                    $payload = $rule->finding($ctx, $result);
                    $payload['scan_id'] = $scan->id;
                    $payload['page_id'] = $page->id;
                    $payload['priority_score'] = $this->priorityScore($rule);
                    $payload['priority_label'] = $this->priorityLabel($payload['priority_score']);
                    $payload['status'] = 'open';

                    $allFindings[] = $payload;
                }
            }

            $scan->update(['progress' => 75]);

            // Persist findings.
            Finding::where('scan_id', $scan->id)->delete();
            foreach (array_chunk($allFindings, 100) as $chunk) {
                Finding::insert($chunk);
            }

            // Score.
            $result = $this->scoring->score($allFindings, $pages->count());

            Score::where('scan_id', $scan->id)->delete();
            foreach ($result['categories'] as $cat => $data) {
                Score::create([
                    'scan_id' => $scan->id,
                    'category' => $cat,
                    'score' => $data['score'],
                    'weight' => $data['weight'],
                    'issues_count' => $data['issues_count'],
                    'evidence_coverage' => $result['evidence_coverage'],
                ]);
            }

            // Recommendations from the highest-priority findings.
            $this->buildRecommendations($scan, $allFindings);

            $scan->update([
                'status' => 'completed',
                'progress' => 100,
                'overall_score' => $result['overall'],
                'confidence' => $result['confidence'],
                'evidence_coverage' => $result['evidence_coverage'],
                'critical_count' => $result['counts']['critical'],
                'high_count' => $result['counts']['high'],
                'medium_count' => $result['counts']['medium'],
                'low_count' => $result['counts']['low'],
                'completed_at' => now(),
                'meta' => [
                    'pages_analysed' => $pages->count(),
                    'rules_evaluated' => count($rules),
                    'sitemap' => $crawl['sitemap'] ?? null,
                ],
            ]);

            $this->log($scan, 'info', "Scan completed. Score {$result['overall']}, ".count($allFindings).' findings.');

            return $scan->fresh();
        } catch (\Throwable $e) {
            $scan->update([
                'status' => 'failed',
                'error_message' => 'Scan error: '.$e->getMessage(),
                'completed_at' => now(),
                'progress' => 100,
            ]);
            $this->log($scan, 'error', 'Unhandled scan error: '.$e->getMessage());
            return $scan->fresh();
        }
    }

    /**
     * Re-fetch the page HTML for analysis.
     *
     * The crawler stores metadata but not raw HTML (to keep the 512 MB database
     * small). We re-fetch each page once during analysis. This is bounded by the
     * same page cap, so it stays within the request budget.
     */
    private function fetchHtmlForAnalysis(Page $page): ?string
    {
        $res = $this->http->get($page->url, ['timeout' => 8]);
        if (! $res['ok'] || ($res['status'] ?? 0) >= 400) {
            return null;
        }
        return $res['body'] ?? null;
    }

    public function priorityScore(Rule $rule): float
    {
        return Priority::score($rule->impact(), $rule->confidence(), $rule->reach(), $rule->effort());
    }

    public function priorityLabel(float $score): string
    {
        return Priority::label($score);
    }

    private function buildRecommendations(Scan $scan, array $findings): void
    {
        Recommendation::where('scan_id', $scan->id)->delete();

        // One recommendation per unique rule, using the highest-priority instance.
        $byRule = [];
        foreach ($findings as $f) {
            $rid = $f['rule_id'];
            if (! isset($byRule[$rid]) || $f['priority_score'] > $byRule[$rid]['priority_score']) {
                $byRule[$rid] = $f;
            }
        }

        usort($byRule, fn ($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        foreach (array_slice($byRule, 0, 25) as $f) {
            Recommendation::create([
                'scan_id' => $scan->id,
                'finding_id' => Finding::where('scan_id', $scan->id)->where('rule_id', $f['rule_id'])->value('id'),
                'title' => $f['recommended_action'],
                'body' => $f['explanation']."\n\n".$f['implementation_guidance'],
                'type' => 'rule_based',
                'priority_label' => $f['priority_label'],
                'priority_score' => $f['priority_score'],
                'impact' => $f['impact'],
                'confidence' => $f['confidence'],
                'effort' => $f['effort'],
                'reach' => $f['reach'],
                'source' => $f['rule_id'],
            ]);
        }
    }

    private function log(Scan $scan, string $level, string $message): void
    {
        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => $level,
            'message' => $message,
            'created_at' => now(),
        ]);
    }
}
