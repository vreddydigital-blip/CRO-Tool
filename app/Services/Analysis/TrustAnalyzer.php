<?php

namespace App\Services\Analysis;

/**
 * Detects visible trust signals.
 *
 * Important distinction: this measures whether proof is VISIBLE on the page.
 * It does not and cannot conclude that a business is untrustworthy — a company
 * may be highly credible and simply not surface proof on this page. Every
 * finding built on this data says "no visible evidence", never "untrustworthy".
 */
class TrustAnalyzer
{
    private const TESTIMONIAL_SELECTORS = [
        'blockquote', '.testimonial', '.testimonials', '.review', '.reviews',
        '[class*="testimonial"]', '[class*="review"]', 'figure.quote',
    ];

    private const LOGO_SELECTORS = [
        '.logos', '.logo-strip', '.clients', '.trusted-by', '[class*="logo"]',
        '[class*="client"]', '[class*="partner"]',
    ];

    public static function analyze(PageContext $page): array
    {
        $testimonials = 0;
        foreach (self::TESTIMONIAL_SELECTORS as $sel) {
            $testimonials += $page->count($sel);
        }

        $logos = 0;
        foreach (self::LOGO_SELECTORS as $sel) {
            $logos += $page->count($sel);
        }

        $text = strtolower($page->bodyText);

        // Contact: phone, email, or address patterns.
        $contact = (bool) preg_match('/\+?\d[\d\s().-]{7,}\d/', $page->bodyText)
            || str_contains($text, '@')
            || $page->has('a[href^="mailto:"], a[href^="tel:"], address');

        $privacy = $page->has('a[href*="privacy"]') || str_contains($text, 'privacy policy');

        $securityWords = ['ssl', 'secure', 'encrypted', 'soc 2', 'soc2', 'gdpr', 'iso 27001', 'money-back', 'money back', 'guarantee', 'refund'];
        $security = false;
        foreach ($securityWords as $w) {
            if (str_contains($text, $w)) {
                $security = true;
                break;
            }
        }

        $caseStudies = str_contains($text, 'case study')
            || str_contains($text, 'case studies')
            || str_contains($text, 'success story')
            || str_contains($text, 'customer story');

        $awardWords = ['award', 'winner', 'rated', 'rating', 'certified', 'accredited', 'top 10', 'best of'];
        $awards = false;
        foreach ($awardWords as $w) {
            if (str_contains($text, $w)) {
                $awards = true;
                break;
            }
        }

        // Attribution: a testimonial block that also names a person/company.
        $attributed = 0;
        if ($testimonials > 0) {
            foreach (['cite', '.author', '.name', '.company', '.role', 'footer'] as $sel) {
                $attributed += $page->count($sel);
            }
        }

        return [
            'testimonials' => $testimonials,
            'attributed_testimonials' => $attributed,
            'logos' => $logos,
            'contact' => $contact,
            'privacy' => $privacy,
            'security' => $security,
            'case_studies' => $caseStudies,
            'awards' => $awards,
        ];
    }

    /** Compact summary for competitor comparison. */
    public static function summary(PageContext $page): array
    {
        $a = self::analyze($page);
        return [
            'testimonials' => $a['testimonials'],
            'logos' => $a['logos'],
            'has_contact' => $a['contact'],
            'has_privacy' => $a['privacy'],
            'has_security' => $a['security'],
            'has_case_studies' => $a['case_studies'],
            'score' => self::score($a),
        ];
    }

    private static function score(array $a): int
    {
        $s = 0;
        if ($a['testimonials'] > 0) $s += 30;
        if ($a['logos'] > 0) $s += 20;
        if ($a['contact']) $s += 20;
        if ($a['privacy']) $s += 10;
        if ($a['security']) $s += 10;
        if ($a['case_studies']) $s += 10;
        return min(100, $s);
    }
}
