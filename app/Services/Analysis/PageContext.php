<?php

namespace App\Services\Analysis;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses a page's HTML once and exposes safe, null-tolerant accessors.
 *
 * Every accessor returns a value or null/[] — never throws. Rules use these so
 * that a malformed page degrades to "cannot inspect" rather than a crash, and
 * so that no rule can accidentally invent evidence for an element that is not
 * present in the DOM.
 */
class PageContext
{
    public readonly Crawler $dom;
    public readonly string $url;
    public readonly string $html;
    public readonly int $statusCode;
    public readonly ?int $loadTimeMs;
    public readonly int $htmlBytes;

    /** @var array<int, array{text:string, href:?string, tag:string, classes:string}> */
    public readonly array $links;
    /** @var array<int, array{text:string, type:string, tag:string}> */
    public readonly array $buttons;
    /** @var array<int, array{action:?string, method:string, fields:array, has_submit:bool, submit_text:?string}> */
    public readonly array $forms;
    /** @var array<int, array{src:?string, alt:?string, has_alt:bool}> */
    public readonly array $images;
    /** @var array<int, array{level:int, text:string}> */
    public readonly array $headings;
    public readonly string $bodyText;
    public readonly int $wordCount;

    public function __construct(string $html, string $url, int $statusCode = 200, ?int $loadTimeMs = null)
    {
        $this->html = $html;
        $this->url = $url;
        $this->statusCode = $statusCode;
        $this->loadTimeMs = $loadTimeMs;
        $this->htmlBytes = strlen($html);

        try {
            $this->dom = new Crawler($html, $url);
        } catch (\Throwable $e) {
            $this->dom = new Crawler('<html><body></body></html>', $url);
        }

        $this->links = $this->extractLinks();
        $this->buttons = $this->extractButtons();
        $this->forms = $this->extractForms();
        $this->images = $this->extractImages();
        $this->headings = $this->extractHeadings();
        $this->bodyText = $this->extractBodyText();
        $this->wordCount = str_word_count($this->bodyText);
    }

    // ---- generic helpers -------------------------------------------------

    public function count(string $selector): int
    {
        try {
            return $this->dom->filter($selector)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function first(string $selector): ?Crawler
    {
        try {
            $node = $this->dom->filter($selector);
            return $node->count() ? $node->first() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function text(string $selector, string $default = ''): string
    {
        $node = $this->first($selector);
        if (! $node) {
            return $default;
        }
        try {
            return trim(preg_replace('/\s+/', ' ', $node->text('')));
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function attr(string $selector, string $attribute): ?string
    {
        $node = $this->first($selector);
        if (! $node) {
            return null;
        }
        try {
            return $node->attr($attribute);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function has(string $selector): bool
    {
        return $this->count($selector) > 0;
    }

    public function title(): ?string
    {
        $t = $this->text('title');
        return $t === '' ? null : $t;
    }

    public function metaDescription(): ?string
    {
        $d = $this->attr('meta[name="description"]', 'content');
        return $d === null || trim($d) === '' ? null : trim($d);
    }

    public function h1(): ?string
    {
        $h = $this->text('h1');
        return $h === '' ? null : $h;
    }

    public function lang(): ?string
    {
        return $this->attr('html', 'lang');
    }

    public function hasViewport(): bool
    {
        return $this->has('meta[name="viewport"]');
    }

    public function canonical(): ?string
    {
        return $this->attr('link[rel="canonical"]', 'href');
    }

    public function hasStructuredData(): bool
    {
        return $this->has('script[type="application/ld+json"]');
    }

    public function hasOpenGraph(): bool
    {
        return $this->has('meta[property^="og:"]');
    }

    public function hasFavicon(): bool
    {
        return $this->has('link[rel*="icon"]');
    }

    /** Text of the first N characters of the body, used for above-the-fold heuristics. */
    public function aboveFoldText(int $chars = 1200): string
    {
        return mb_substr($this->bodyText, 0, $chars);
    }

    /** All visible text of a selector list, joined. */
    public function textOfAll(string $selector): string
    {
        try {
            $parts = [];
            $this->dom->filter($selector)->each(function (Crawler $n) use (&$parts) {
                $parts[] = trim(preg_replace('/\s+/', ' ', $n->text('')));
            });
            return implode(' ', array_filter($parts));
        } catch (\Throwable $e) {
            return '';
        }
    }

    // ---- extractors ------------------------------------------------------

    private function extractLinks(): array
    {
        $out = [];
        try {
            $this->dom->filter('a[href]')->each(function (Crawler $n) use (&$out) {
                $out[] = [
                    'text' => trim(preg_replace('/\s+/', ' ', $n->text(''))),
                    'href' => $n->attr('href'),
                    'tag' => 'a',
                    'classes' => (string) $n->attr('class'),
                ];
            });
        } catch (\Throwable $e) {
        }
        return $out;
    }

    private function extractButtons(): array
    {
        $out = [];
        try {
            $this->dom->filter('button, input[type="submit"], input[type="button"], a[role="button"]')->each(function (Crawler $n) use (&$out) {
                $tag = $n->nodeName();
                $text = $tag === 'input'
                    ? (string) ($n->attr('value') ?? '')
                    : trim(preg_replace('/\s+/', ' ', $n->text('')));
                $out[] = [
                    'text' => $text,
                    'type' => (string) ($n->attr('type') ?? 'button'),
                    'tag' => $tag,
                ];
            });
        } catch (\Throwable $e) {
        }
        return $out;
    }

    private function extractForms(): array
    {
        $out = [];
        try {
            $this->dom->filter('form')->each(function (Crawler $form) use (&$out) {
                $fields = [];
                $form->filter('input, select, textarea')->each(function (Crawler $f) use (&$fields) {
                    $type = strtolower((string) ($f->attr('type') ?? 'text'));
                    if (in_array($type, ['hidden', 'submit', 'button', 'reset'], true)) {
                        return;
                    }
                    $id = $f->attr('id');
                    $label = null;
                    if ($id) {
                        $labelNode = $f->getNode(0)->ownerDocument->getElementById($id);
                        if ($labelNode) {
                            $labels = $f->getNode(0)->ownerDocument->getElementsByTagName('label');
                            foreach ($labels as $l) {
                                if ($l->getAttribute('for') === $id) {
                                    $label = trim($l->textContent);
                                    break;
                                }
                            }
                        }
                    }
                    $fields[] = [
                        'name' => (string) ($f->attr('name') ?? ''),
                        'type' => $type,
                        'label' => $label,
                        'placeholder' => $f->attr('placeholder'),
                        'required' => $f->attr('required') !== null,
                        'autocomplete' => $f->attr('autocomplete'),
                    ];
                });

                $submit = $form->filter('button[type="submit"], input[type="submit"], button');
                $submitText = null;
                if ($submit->count()) {
                    $s = $submit->first();
                    $submitText = $s->nodeName() === 'input'
                        ? (string) ($s->attr('value') ?? '')
                        : trim($s->text(''));
                }

                $out[] = [
                    'action' => $form->attr('action'),
                    'method' => strtoupper((string) ($form->attr('method') ?? 'GET')),
                    'fields' => $fields,
                    'has_submit' => $submit->count() > 0,
                    'submit_text' => $submitText,
                ];
            });
        } catch (\Throwable $e) {
        }
        return $out;
    }

    private function extractImages(): array
    {
        $out = [];
        try {
            $this->dom->filter('img')->each(function (Crawler $n) use (&$out) {
                $alt = $n->attr('alt');
                $out[] = [
                    'src' => $n->attr('src'),
                    'alt' => $alt,
                    'has_alt' => $alt !== null && trim($alt) !== '',
                ];
            });
        } catch (\Throwable $e) {
        }
        return $out;
    }

    private function extractHeadings(): array
    {
        $out = [];
        try {
            $this->dom->filter('h1, h2, h3, h4, h5, h6')->each(function (Crawler $n) use (&$out) {
                $out[] = [
                    'level' => (int) substr($n->nodeName(), 1),
                    'text' => trim(preg_replace('/\s+/', ' ', $n->text(''))),
                ];
            });
        } catch (\Throwable $e) {
        }
        return $out;
    }

    private function extractBodyText(): string
    {
        try {
            $node = $this->first('body');
            if (! $node) {
                return '';
            }
            // Remove script/style noise before counting words.
            $html = $node->html();
            $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
            $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
            $text = strip_tags($html);
            return trim(preg_replace('/\s+/', ' ', html_entity_decode($text)));
        } catch (\Throwable $e) {
            return '';
        }
    }
}
