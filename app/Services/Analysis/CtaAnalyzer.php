<?php

namespace App\Services\Analysis;

/**
 * Detects and classifies calls to action.
 *
 * A CTA is either a <button>, a submit input, or a link whose text or classes
 * mark it as an action. We classify by intent so the UI can show a CTA
 * hierarchy rather than a flat list.
 */
class CtaAnalyzer
{
    /** Intent buckets, matched against the label. */
    private const INTENTS = [
        'contact' => ['contact', 'get in touch', 'reach out', 'email us', 'call us'],
        'enquiry' => ['enquire', 'inquire', 'enquiry', 'inquiry', 'ask a question', 'get a quote'],
        'book' => ['book', 'schedule', 'reserve', 'appointment', 'calendar'],
        'buy' => ['buy', 'purchase', 'order now', 'add to cart', 'checkout'],
        'register' => ['register', 'sign up', 'signup', 'create account', 'join'],
        'download' => ['download', 'get the guide', 'get the ebook', 'pdf'],
        'demo' => ['demo', 'request a demo', 'book a demo', 'see it in action'],
        'consultation' => ['consultation', 'consult', 'strategy call', 'discovery call'],
        'subscribe' => ['subscribe', 'newsletter', 'stay updated'],
        'trial' => ['free trial', 'start free', 'try free', 'try it free'],
    ];

    private const PRIMARY_CLASSES = ['btn-primary', 'button-primary', 'cta', 'cta-primary', 'primary', 'btn-cta', 'btn-accent'];

    /**
     * @return array<int, array{text:string, type:string, intent:string, href:?string, is_primary:bool, tag:string}>
     */
    public static function detect(PageContext $page): array
    {
        $out = [];
        $seen = [];

        // Buttons and submit inputs.
        foreach ($page->buttons as $b) {
            $text = trim($b['text']);
            if ($text === '') {
                continue;
            }
            $key = strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'text' => $text,
                'type' => 'button',
                'intent' => self::intent($text),
                'href' => null,
                'is_primary' => true,
                'tag' => $b['tag'],
            ];
        }

        // Links that look like CTAs.
        foreach ($page->links as $l) {
            $text = trim($l['text']);
            if ($text === '' || str_word_count($text) > 8) {
                continue;
            }

            $classes = strtolower($l['classes']);
            $isButtonish = false;
            foreach (self::PRIMARY_CLASSES as $c) {
                if (str_contains($classes, $c)) {
                    $isButtonish = true;
                    break;
                }
            }

            $intent = self::intent($text);
            if (! $isButtonish && $intent === 'other') {
                continue;
            }

            $key = strtolower($text).'|'.($l['href'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $out[] = [
                'text' => $text,
                'type' => 'link',
                'intent' => $intent,
                'href' => $l['href'],
                'is_primary' => $isButtonish,
                'tag' => 'a',
            ];
        }

        return $out;
    }

    public static function intent(string $text): string
    {
        $t = strtolower($text);
        foreach (self::INTENTS as $intent => $phrases) {
            foreach ($phrases as $p) {
                if (str_contains($t, $p)) {
                    return $intent;
                }
            }
        }
        return 'other';
    }

    /** Summary used by the dashboard and competitor comparison. */
    public static function summary(PageContext $page): array
    {
        $ctas = self::detect($page);
        $intents = array_count_values(array_column($ctas, 'intent'));

        return [
            'total' => count($ctas),
            'primary' => count(array_filter($ctas, fn ($c) => $c['is_primary'])),
            'intents' => $intents,
            'labels' => array_slice(array_values(array_unique(array_column($ctas, 'text'))), 0, 10),
            'has_primary_intent' => isset($intents['trial']) || isset($intents['demo']) || isset($intents['buy']) || isset($intents['register']) || isset($intents['contact']),
        ];
    }
}
