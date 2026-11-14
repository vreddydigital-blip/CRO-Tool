<?php

namespace App\Services\Crawler;

use App\Models\Page;
use App\Models\Scan;
use App\Services\Security\SafeHttpClient;
use App\Services\Security\UrlValidator;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Bounded, polite PHP crawler.
 *
 * Free-plan constraints (EthioDeploy: 256 MB RAM, 0.5 CPU, 30 s boot, no
 * persistent workers) mean the crawl MUST be small and synchronous:
 *   - max 10 pages per scan
 *   - max depth 2
 *   - 10 s per-request timeout, 2 MB per page
 *   - hard wall-clock budget so a slow site cannot exhaust the request
 */
class SiteCrawler
{
    public const MAX_PAGES = 10;
    public const MAX_DEPTH = 2;

    private array $visited = [];
    private array $queue = [];
    private int $pagesCrawled = 0;
    private int $pagesFailed = 0;
    private float $deadline;
    private array $robots = [];
    private ?string $sitemapUrl = null;

    public function __construct(
        private SafeHttpClient $http,
        private UrlValidator $validator,
        private int $maxPages = self::MAX_PAGES,
        private int $maxDepth = self::MAX_DEPTH,
        private int $timeBudgetSeconds = 45,
    ) {}

    /**
     * Crawl a site and persist pages. Returns a summary array.
     */
    public function crawl(Scan $scan, string $startUrl): array
    {
        $this->deadline = microtime(true) + $this->timeBudgetSeconds;
        $this->visited = [];
        $this->queue = [];
        $this->pagesCrawled = 0;
        $this->pagesFailed = 0;

        $check = $this->validator->validate($startUrl);
        if (! $check['ok']) {
            $this->log($scan, 'error', 'Start URL rejected: '.$check['error']);
            return ['ok' => false, 'error' => $check['error'], 'pages' => 0];
        }

        $startUrl = $check['url'];
        $origin = $this->origin($startUrl);

        $this->loadRobots($scan, $origin);
        $this->loadSitemap($scan, $origin);

        $this->queue[] = ['url' => $startUrl, 'depth' => 0];

        while ($this->queue !== [] && $this->pagesCrawled < $this->maxPages) {
            if (microtime(true) > $this->deadline) {
                $this->log($scan, 'warn', 'Time budget exhausted; stopping crawl.');
                break;
            }

            $item = array_shift($this->queue);
            $url = $item['url'];
            $depth = $item['depth'];
            $norm = $this->normalizeForDedupe($url);

            if (isset($this->visited[$norm])) {
                continue;
            }
            $this->visited[$norm] = true;

            if (! $this->isAllowedByRobots($url)) {
                $this->log($scan, 'info', "Skipped by robots.txt: {$url}");
                continue;
            }

            $result = $this->http->get($url);

            if (! $result['ok']) {
                $this->pagesFailed++;
                $this->log($scan, 'warn', "Fetch failed: {$url} — {$result['error']}");
                $this->storePage($scan, $url, $norm, null, $depth, null, $result['error']);
                continue;
            }

            $status = $result['status'];
            $body = $result['body'] ?? '';

            if ($status >= 400) {
                $this->pagesFailed++;
                $this->log($scan, 'warn', "HTTP {$status}: {$url}");
                $this->storePage($scan, $url, $norm, $status, $depth, null, "HTTP {$status}");
                continue;
            }

            $contentType = $result['headers']['content-type'] ?? '';
            if ($contentType !== '' && ! str_contains(strtolower($contentType), 'html')) {
                $this->log($scan, 'info', "Skipped non-HTML: {$url} ({$contentType})");
                continue;
            }

            $page = $this->storePage($scan, $url, $norm, $status, $depth, $body, null, $result);
            $this->pagesCrawled++;

            if ($depth < $this->maxDepth) {
                foreach ($this->extractLinks($body, $result['final_url'] ?? $url, $origin) as $link) {
                    $linkNorm = $this->normalizeForDedupe($link);
                    if (! isset($this->visited[$linkNorm])) {
                        $this->queue[] = ['url' => $link, 'depth' => $depth + 1];
                    }
                }
            }
        }

        $this->log($scan, 'info', "Crawl finished: {$this->pagesCrawled} pages, {$this->pagesFailed} failed.");

        return [
            'ok' => true,
            'pages' => $this->pagesCrawled,
            'failed' => $this->pagesFailed,
            'sitemap' => $this->sitemapUrl,
        ];
    }

    private function storePage(Scan $scan, string $url, string $norm, ?int $status, int $depth, ?string $body, ?string $error, array $result = []): Page
    {
        $title = null;
        $metaDesc = null;
        $wordCount = 0;
        $hash = null;

        if ($body !== null && $body !== '') {
            $hash = hash('sha256', $body);
            try {
                $crawler = new Crawler($body);
                $title = $crawler->filter('title')->count() ? trim($crawler->filter('title')->text('')) : null;
                $metaDesc = $crawler->filter('meta[name="description"]')->count()
                    ? trim($crawler->filter('meta[name="description"]')->attr('content') ?? '')
                    : null;
                $text = $crawler->filter('body')->count() ? $crawler->filter('body')->text('') : '';
                $wordCount = str_word_count(preg_replace('/\s+/', ' ', $text));
            } catch (\Throwable $e) {
                $this->log($scan, 'warn', "DOM parse failed for {$url}: ".$e->getMessage());
            }
        }

        return Page::updateOrCreate(
            ['scan_id' => $scan->id, 'normalized_url' => $norm],
            [
                'url' => $url,
                'status_code' => $status,
                'depth' => $depth,
                'title' => $title ? mb_substr($title, 0, 500) : null,
                'meta_description' => $metaDesc,
                'word_count' => $wordCount,
                'html_hash' => $hash,
                'is_primary' => $depth === 0,
                'load_time_ms' => $result['time_ms'] ?? null,
                'html_bytes' => $result['bytes'] ?? null,
                'error' => $error,
                'fetched_at' => now(),
            ]
        );
    }

    /** @return string[] */
    private function extractLinks(string $html, string $baseUrl, string $origin): array
    {
        $links = [];

        try {
            $crawler = new Crawler($html, $baseUrl);
            $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links, $origin) {
                $href = trim($node->attr('href') ?? '');
                if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')
                    || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                    return;
                }

                $abs = $this->absolutize($baseUrl, $href);
                if ($abs === null) {
                    return;
                }

                // Same-origin only: we never crawl a third-party site.
                if ($this->origin($abs) !== $origin) {
                    return;
                }

                // Skip obvious non-pages.
                if (preg_match('/\.(jpg|jpeg|png|gif|svg|webp|pdf|zip|mp4|mp3|css|js|ico|woff2?)$/i', parse_url($abs, PHP_URL_PATH) ?? '')) {
                    return;
                }

                $links[] = $abs;
            });
        } catch (\Throwable $e) {
            // Malformed HTML: return what we have.
        }

        return array_values(array_unique($links));
    }

    private function absolutize(string $base, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $p = parse_url($base);
        if (! $p || empty($p['host'])) {
            return null;
        }

        $root = ($p['scheme'] ?? 'https').'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');

        if (str_starts_with($href, '//')) {
            return ($p['scheme'] ?? 'https').':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $root.$href;
        }

        $dir = rtrim(dirname($p['path'] ?? '/'), '/');

        return $root.$dir.'/'.$href;
    }

    private function origin(string $url): string
    {
        $p = parse_url($url);
        return strtolower(($p['scheme'] ?? 'https').'://'.($p['host'] ?? ''));
    }

    /** Dedupe key: origin + path, ignoring query/fragment/trailing slash. */
    private function normalizeForDedupe(string $url): string
    {
        $p = parse_url($url);
        $path = rtrim($p['path'] ?? '/', '/');
        if ($path === '') {
            $path = '/';
        }
        return strtolower(($p['host'] ?? '').$path);
    }

    private function loadRobots(Scan $scan, string $origin): void
    {
        $res = $this->http->get($origin.'/robots.txt', ['timeout' => 5]);

        if (! $res['ok'] || $res['status'] !== 200) {
            $this->log($scan, 'info', 'No robots.txt found; proceeding with defaults.');
            return;
        }

        $this->robots = $this->parseRobots($res['body'] ?? '');
        $this->log($scan, 'info', 'robots.txt loaded.');
    }

    private function parseRobots(string $body): array
    {
        $rules = ['disallow' => [], 'allow' => [], 'crawl_delay' => null];
        $applies = false;

        foreach (preg_split("/\r\n|\n|\r/", $body) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if ($line === '') {
                continue;
            }

            if (preg_match('/^user-agent:\s*(.*)$/i', $line, $m)) {
                $ua = strtolower(trim($m[1]));
                $applies = ($ua === '*' || str_contains($ua, 'crointelligence'));
                continue;
            }

            if (! $applies) {
                continue;
            }

            if (preg_match('/^disallow:\s*(.*)$/i', $line, $m)) {
                $path = trim($m[1]);
                if ($path !== '') {
                    $rules['disallow'][] = $path;
                }
            } elseif (preg_match('/^allow:\s*(.*)$/i', $line, $m)) {
                $path = trim($m[1]);
                if ($path !== '') {
                    $rules['allow'][] = $path;
                }
            } elseif (preg_match('/^crawl-delay:\s*(\d+)/i', $line, $m)) {
                $rules['crawl_delay'] = (int) $m[1];
            }
        }

        return $rules;
    }

    private function isAllowedByRobots(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        foreach ($this->robots['allow'] ?? [] as $allow) {
            if ($allow !== '' && str_starts_with($path, $allow)) {
                return true;
            }
        }

        foreach ($this->robots['disallow'] ?? [] as $disallow) {
            if ($disallow === '/') {
                return false;
            }
            if ($disallow !== '' && str_starts_with($path, $disallow)) {
                return false;
            }
        }

        return true;
    }

    private function loadSitemap(Scan $scan, string $origin): void
    {
        $res = $this->http->get($origin.'/sitemap.xml', ['timeout' => 5]);

        if (! $res['ok'] || $res['status'] !== 200) {
            $this->log($scan, 'info', 'No sitemap.xml found.');
            return;
        }

        $this->sitemapUrl = $origin.'/sitemap.xml';
        $this->log($scan, 'info', 'sitemap.xml found.');

        // Seed the queue with sitemap URLs (bounded), after the start page.
        try {
            $xml = @simplexml_load_string($res['body'] ?? '');
            if ($xml === false) {
                return;
            }

            $urls = [];
            if (isset($xml->url)) {
                foreach ($xml->url as $u) {
                    $urls[] = (string) $u->loc;
                }
            } elseif (isset($xml->sitemap)) {
                // Index file: take the first child sitemap only (bounded).
                $first = (string) $xml->sitemap[0]->loc;
                $child = $this->http->get($first, ['timeout' => 5]);
                if ($child['ok'] && $child['status'] === 200) {
                    $cx = @simplexml_load_string($child['body'] ?? '');
                    if ($cx !== false && isset($cx->url)) {
                        foreach ($cx->url as $u) {
                            $urls[] = (string) $u->loc;
                        }
                    }
                }
            }

            $count = 0;
            foreach ($urls as $u) {
                if ($count >= 5) {
                    break;
                }
                if ($this->origin($u) === $origin) {
                    $this->queue[] = ['url' => $u, 'depth' => 1];
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            $this->log($scan, 'warn', 'Sitemap parse failed: '.$e->getMessage());
        }
    }

    private function log(Scan $scan, string $level, string $message): void
    {
        \App\Models\ScanLog::create([
            'scan_id' => $scan->id,
            'level' => $level,
            'message' => $message,
            'created_at' => now(),
        ]);
    }
}
