<?php

namespace App\Services\Analysis;

/**
 * The CRO rule library: 65 rules across 10 categories.
 *
 * Design contract for every rule:
 *  - It only fires when the element it inspects is actually present in the DOM.
 *    If a page has no form, form rules stay silent rather than claiming a
 *    missing form is a defect.
 *  - Evidence quotes what was observed (text, counts, attribute values).
 *  - Limitations state what could not be measured (JS, layout, real analytics).
 */
class RuleLibrary
{
    /** @return Rule[] */
    public static function all(): array
    {
        return array_merge(
            self::valueProposition(),
            self::cta(),
            self::trust(),
            self::forms(),
            self::ux(),
            self::navigation(),
            self::mobile(),
            self::content(),
            self::technical(),
            self::performance(),
        );
    }

    private static function r(string $id, string $cat, string $name, string $sev, int $conf, int $impact, int $effort, int $reach, \Closure $fn, ?string $lim = null): Rule
    {
        return new ClosureRule($id, $cat, $name, $sev, $conf, $impact, $effort, $reach, $fn, $lim ?? 'Based on server-rendered HTML only; JavaScript-injected content and visual layout were not evaluated.');
    }

    // =====================================================================
    // VALUE PROPOSITION (20%)
    // =====================================================================
    private static function valueProposition(): array
    {
        return [
            self::r('VP-001', 'value_proposition', 'Missing H1 headline', 'critical', 5, 5, 2, 5,
                function (PageContext $p) {
                    if ($p->h1() !== null) return null;
                    return [
                        'evidence' => 'No <h1> element found in the page HTML.',
                        'explanation' => 'The H1 is the first thing a visitor reads and the primary signal of what the page offers. Without one, visitors must infer the offer from body copy, which increases bounce.',
                        'action' => 'Add a single, benefit-led H1 that states what the product does and for whom.',
                        'guidance' => 'Place one <h1> at the top of the main content. Keep it under ~12 words. Example: "Cut checkout abandonment with one-line integrations."',
                    ];
                }),

            self::r('VP-002', 'value_proposition', 'Multiple H1 elements compete', 'medium', 4, 3, 1, 4,
                function (PageContext $p) {
                    $n = $p->count('h1');
                    if ($n <= 1) return null;
                    return [
                        'evidence' => "Found {$n} <h1> elements on the page.",
                        'explanation' => 'Multiple H1s split the visual and semantic hierarchy, so no single message leads. Visitors scan the largest text first and get conflicting signals.',
                        'action' => 'Demote secondary headings to H2 and keep one H1 as the page promise.',
                        'guidance' => 'Keep exactly one <h1>. Convert section titles to <h2>.',
                    ];
                }),

            self::r('VP-003', 'value_proposition', 'H1 is too long to scan', 'medium', 4, 3, 1, 4,
                function (PageContext $p) {
                    $h = $p->h1();
                    if ($h === null) return null;
                    $words = str_word_count($h);
                    if ($words <= 16) return null;
                    return [
                        'evidence' => "H1 is {$words} words: \"".mb_substr($h, 0, 160).'"',
                        'explanation' => 'Headlines longer than ~16 words are rarely read in full, especially on mobile where they wrap to many lines.',
                        'action' => 'Shorten the H1 to a single scannable promise of 6–12 words.',
                        'guidance' => 'Lead with the outcome, not the company name. Move qualifiers into a supporting subhead.',
                    ];
                }),

            self::r('VP-004', 'value_proposition', 'No supporting subheadline', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    if ($p->h1() === null) return null;
                    $sub = $p->text('h1 + p, h1 + h2, .subtitle, .subheadline, .tagline');
                    if ($sub !== '') return null;
                    return [
                        'evidence' => 'No paragraph or subheading immediately follows the H1.',
                        'explanation' => 'A subheadline explains how the promise is delivered. Without it, the H1 carries the whole burden of clarity.',
                        'action' => 'Add one sentence under the H1 explaining the mechanism or the audience.',
                        'guidance' => 'One sentence, 15–25 words, answering "how" or "for whom".',
                    ];
                }),

            self::r('VP-005', 'value_proposition', 'Value proposition not visible above the fold', 'high', 3, 4, 3, 5,
                function (PageContext $p) {
                    $fold = strtolower($p->aboveFoldText(900));
                    if ($fold === '') return null;
                    $signals = ['save', 'increase', 'reduce', 'grow', 'faster', 'cheaper', 'free', 'guarantee', 'within', 'per ', '%'];
                    foreach ($signals as $s) {
                        if (str_contains($fold, $s)) return null;
                    }
                    return [
                        'evidence' => 'The first ~900 characters of body text contain no outcome or benefit language.',
                        'explanation' => 'Visitors decide within seconds whether to stay. If the opening copy describes the company rather than the visitor\'s outcome, most leave before scrolling.',
                        'action' => 'Rewrite the opening copy to lead with a concrete, quantified benefit.',
                        'guidance' => 'Use a benefit + proof structure: "Ship campaigns 3× faster — used by 400+ growth teams."',
                    ];
                }),

            self::r('VP-006', 'value_proposition', 'No audience qualifier', 'low', 3, 2, 2, 3,
                function (PageContext $p) {
                    $text = strtolower($p->aboveFoldText(1500));
                    if ($text === '') return null;
                    $aud = ['for teams', 'for marketers', 'for agencies', 'for startups', 'for enterprises', 'for developers', 'for founders', 'for smbs', 'for ecommerce', 'for retailers', 'built for', 'designed for'];
                    foreach ($aud as $a) {
                        if (str_contains($text, $a)) return null;
                    }
                    return [
                        'evidence' => 'No explicit audience qualifier found in the opening copy.',
                        'explanation' => 'Naming the audience helps the right visitor self-identify and reduces unqualified traffic.',
                        'action' => 'State who the product is for in the hero section.',
                        'guidance' => 'Add a short qualifier such as "Built for B2B SaaS marketing teams".',
                    ];
                }),

            self::r('VP-007', 'value_proposition', 'No quantified claim or proof point in hero', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $fold = $p->aboveFoldText(1200);
                    if ($fold === '') return null;
                    if (preg_match('/\d+\s*(%|x|k|m|hours?|days?|minutes?|users?|customers?|teams?)/i', $fold)) return null;
                    return [
                        'evidence' => 'No number, percentage or unit-based claim in the opening copy.',
                        'explanation' => 'Specific numbers are more credible and memorable than adjectives. "Fast" is forgettable; "under 2 minutes" is not.',
                        'action' => 'Add one concrete metric to the hero copy or a proof strip beneath it.',
                        'guidance' => 'Use a real, defensible number. Never invent one — if you lack data, use a customer count or a time-to-value figure.',
                    ];
                }),

            self::r('VP-008', 'value_proposition', 'Generic marketing language in hero', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $fold = strtolower($p->aboveFoldText(1000));
                    if ($fold === '') return null;
                    $buzz = ['cutting-edge', 'world-class', 'best-in-class', 'next-generation', 'revolutionary', 'seamless', 'synergy', 'leverage', 'innovative solutions', 'state-of-the-art', 'game-changing'];
                    $hits = array_values(array_filter($buzz, fn ($b) => str_contains($fold, $b)));
                    if ($hits === []) return null;
                    return [
                        'evidence' => 'Buzzwords detected in opening copy: '.implode(', ', $hits).'.',
                        'explanation' => 'Abstract superlatives carry no information and read as filler. They reduce trust with experienced buyers.',
                        'action' => 'Replace each buzzword with a concrete, verifiable statement.',
                        'guidance' => 'Swap "seamless integration" for "connects to HubSpot in one click".',
                    ];
                }),

            self::r('VP-009', 'value_proposition', 'Page title does not match the offer', 'low', 3, 2, 1, 3,
                function (PageContext $p) {
                    $t = $p->title();
                    $h = $p->h1();
                    if ($t === null || $h === null) return null;
                    $tw = array_unique(array_filter(preg_split('/\W+/', strtolower($t)), fn ($w) => strlen($w) > 3));
                    $hw = array_unique(array_filter(preg_split('/\W+/', strtolower($h)), fn ($w) => strlen($w) > 3));
                    if (array_intersect($tw, $hw) !== []) return null;
                    return [
                        'evidence' => "Title: \"{$t}\" — H1: \"{$h}\" share no significant keywords.",
                        'explanation' => 'A mismatch between the tab title and the page headline suggests the page was repurposed or the title is stale, which confuses returning visitors and search snippets.',
                        'action' => 'Align the <title> with the H1 message.',
                        'guidance' => 'Title format: "Primary benefit | Brand".',
                    ];
                }),

            self::r('VP-010', 'value_proposition', 'No differentiation statement', 'low', 2, 2, 3, 3,
                function (PageContext $p) {
                    $text = strtolower($p->bodyText);
                    if ($text === '') return null;
                    $diff = ['unlike', 'instead of', 'compared to', 'the only', 'only platform', 'no other', 'difference', 'why we', 'why us'];
                    foreach ($diff as $d) {
                        if (str_contains($text, $d)) return null;
                    }
                    return [
                        'evidence' => 'No comparative or differentiation language found in page copy.',
                        'explanation' => 'Without a stated difference, visitors default to price comparison against the most familiar alternative.',
                        'action' => 'Add a short "why us" or comparison section.',
                        'guidance' => 'Name the alternative and the specific advantage. Keep claims verifiable.',
                    ];
                }),
        ];
    }

    // =====================================================================
    // CTA EFFECTIVENESS (20%)
    // =====================================================================
    private static function cta(): array
    {
        return [
            self::r('CTA-001', 'cta', 'No call to action detected', 'critical', 5, 5, 2, 5,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if ($ctas !== []) return null;
                    return [
                        'evidence' => 'No button, submit input, or CTA-styled link was found in the HTML.',
                        'explanation' => 'A page without a call to action gives the visitor nothing to do next, so intent has no outlet.',
                        'action' => 'Add one primary CTA above the fold and repeat it at the end of each major section.',
                        'guidance' => 'Use a <button> or a link styled as a button. Label it with the outcome, e.g. "Start free trial".',
                    ];
                }),

            self::r('CTA-002', 'cta', 'No primary CTA above the fold', 'high', 4, 4, 2, 5,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if ($ctas === []) return null;
                    $fold = strtolower($p->aboveFoldText(700));
                    foreach ($ctas as $c) {
                        $t = strtolower(trim($c['text']));
                        if ($t !== '' && str_contains($fold, $t)) return null;
                    }
                    return [
                        'evidence' => 'CTA labels found ('.implode(', ', array_slice(array_column($ctas, 'text'), 0, 4)).') do not appear in the first ~700 characters of body text.',
                        'explanation' => 'Visitors who are convinced by the hero should not have to scroll to act. A missing above-fold CTA loses the highest-intent moment.',
                        'action' => 'Place the primary CTA in the hero, adjacent to the headline.',
                        'guidance' => 'One primary button, visually dominant, with a secondary text link beside it.',
                    ];
                }),

            self::r('CTA-003', 'cta', 'Generic CTA label', 'high', 4, 4, 1, 5,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if ($ctas === []) return null;
                    $generic = ['submit', 'click here', 'learn more', 'read more', 'more', 'go', 'send', 'continue', 'next', 'ok', 'here'];
                    $hits = [];
                    foreach ($ctas as $c) {
                        $t = strtolower(trim($c['text']));
                        if (in_array($t, $generic, true)) $hits[] = $c['text'];
                    }
                    if ($hits === []) return null;
                    return [
                        'evidence' => 'Generic CTA labels: "'.implode('", "', array_unique($hits)).'".',
                        'explanation' => 'Generic labels describe the click, not the value received. Outcome-oriented labels consistently outperform them in A/B tests.',
                        'action' => 'Rewrite each generic label to state the outcome.',
                        'guidance' => '"Learn more" → "See how it works"; "Submit" → "Get my audit".',
                    ];
                }),

            self::r('CTA-004', 'cta', 'Too many competing CTAs', 'high', 4, 4, 2, 5,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    $primary = array_filter($ctas, fn ($c) => $c['is_primary']);
                    if (count($primary) <= 3) return null;
                    return [
                        'evidence' => count($primary).' primary-styled CTAs detected on the page.',
                        'explanation' => 'When several buttons compete for attention, visitors hesitate. Hick\'s law: more choices increase decision time and abandonment.',
                        'action' => 'Designate one primary action and demote the rest to secondary links.',
                        'guidance' => 'One filled button per viewport; everything else as outline or text link.',
                    ];
                }),

            self::r('CTA-005', 'cta', 'CTA has no clear destination', 'medium', 4, 3, 2, 4,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if ($ctas === []) return null;
                    $bad = [];
                    foreach ($ctas as $c) {
                        $href = $c['href'] ?? null;
                        if ($href === null || $href === '' || $href === '#' || str_starts_with($href, 'javascript:')) {
                            $bad[] = $c['text'];
                        }
                    }
                    if ($bad === []) return null;
                    return [
                        'evidence' => 'CTAs with empty or placeholder destinations: "'.implode('", "', array_unique($bad)).'".',
                        'explanation' => 'A CTA that goes nowhere (or to "#") breaks the conversion path and signals an unfinished page.',
                        'action' => 'Point every CTA at a real, relevant destination.',
                        'guidance' => 'Link to a signup, contact, or booking page. Verify the target returns 200.',
                    ];
                }),

            self::r('CTA-006', 'cta', 'CTA text is too long', 'low', 3, 2, 1, 3,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    $long = [];
                    foreach ($ctas as $c) {
                        if (str_word_count($c['text']) > 6) $long[] = $c['text'];
                    }
                    if ($long === []) return null;
                    return [
                        'evidence' => 'Long CTA labels: "'.implode('", "', array_unique($long)).'".',
                        'explanation' => 'Labels over ~6 words wrap on mobile and lose visual weight as buttons.',
                        'action' => 'Trim CTA labels to 2–5 words.',
                        'guidance' => 'Keep the verb and the outcome; drop qualifiers.',
                    ];
                }),

            self::r('CTA-007', 'cta', 'No secondary CTA for lower-intent visitors', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if (count($ctas) < 1) return null;
                    $secondary = array_filter($ctas, fn ($c) => ! $c['is_primary']);
                    if ($secondary !== []) return null;
                    return [
                        'evidence' => 'All detected CTAs are primary-styled; no secondary or low-commitment action found.',
                        'explanation' => 'Visitors not ready to convert need a lower-commitment next step (demo, guide, pricing). Without one they leave.',
                        'action' => 'Add a secondary action for lower-intent visitors.',
                        'guidance' => 'Pair the primary CTA with a text link such as "See pricing" or "Watch a 2-min demo".',
                    ];
                }),

            self::r('CTA-008', 'cta', 'CTA lacks action verb', 'medium', 3, 3, 1, 4,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if ($ctas === []) return null;
                    $verbs = ['get', 'start', 'book', 'buy', 'try', 'request', 'download', 'join', 'see', 'view', 'talk', 'contact', 'schedule', 'claim', 'create', 'build', 'explore', 'compare', 'watch', 'read', 'subscribe', 'sign'];
                    $bad = [];
                    foreach ($ctas as $c) {
                        $first = strtolower(strtok(trim($c['text']), ' ') ?: '');
                        if ($first !== '' && ! in_array($first, $verbs, true)) $bad[] = $c['text'];
                    }
                    if ($bad === []) return null;
                    return [
                        'evidence' => 'CTA labels not starting with an action verb: "'.implode('", "', array_unique($bad)).'".',
                        'explanation' => 'Verb-first labels read as instructions and are easier to act on than noun phrases.',
                        'action' => 'Start each CTA with a strong verb.',
                        'guidance' => '"Free trial" → "Start free trial".',
                    ];
                }),

            self::r('CTA-009', 'cta', 'Repeated identical CTA without variation', 'low', 2, 2, 2, 3,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if (count($ctas) < 4) return null;
                    $texts = array_map(fn ($c) => strtolower(trim($c['text'])), $ctas);
                    $unique = array_unique($texts);
                    if (count($unique) > 1) return null;
                    return [
                        'evidence' => count($ctas).' CTAs all use the same label: "'.$ctas[0]['text'].'".',
                        'explanation' => 'Repeating one label everywhere is fine for consistency, but varying the label by section context can match the visitor\'s stage of intent.',
                        'action' => 'Keep the primary label consistent but tailor section-level CTAs to context.',
                        'guidance' => 'Hero: "Start free trial". Pricing: "Choose plan". Bottom: "Talk to sales".',
                    ];
                }),

            self::r('CTA-010', 'cta', 'CTA contrast or size not distinguishable', 'medium', 2, 3, 2, 4,
                function (PageContext $p) {
                    $ctas = CtaAnalyzer::detect($p);
                    if ($ctas === []) return null;
                    $styled = array_filter($ctas, fn ($c) => $c['is_primary']);
                    if ($styled !== []) return null;
                    return [
                        'evidence' => 'No CTA carries button-like styling classes (btn, button, cta) in the HTML.',
                        'explanation' => 'If CTAs are plain text links, they do not stand out from surrounding copy and are easily missed.',
                        'action' => 'Style the primary CTA as a distinct button with strong contrast.',
                        'guidance' => 'Filled background, 44px+ height, high contrast against the page background.',
                    ];
                }, 'Styling was inferred from class names only; actual rendered contrast and size were not measured.'),
        ];
    }

    // =====================================================================
    // TRUST (15%)
    // =====================================================================
    private static function trust(): array
    {
        return [
            self::r('TR-001', 'trust', 'No testimonials or reviews visible', 'high', 4, 4, 3, 5,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['testimonials'] > 0) return null;
                    return [
                        'evidence' => 'No testimonial, review, or quote block detected in the HTML.',
                        'explanation' => 'Social proof reduces perceived risk. Its absence is most costly for first-time visitors who have no prior relationship with the brand.',
                        'action' => 'Add 2–3 specific customer quotes with name, role and company.',
                        'guidance' => 'Place one near the hero and one near the primary CTA. Specificity beats volume.',
                    ];
                }),

            self::r('TR-002', 'trust', 'No client or partner logos', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['logos'] > 0) return null;
                    return [
                        'evidence' => 'No logo strip or "trusted by" section detected.',
                        'explanation' => 'Recognisable logos act as instant credibility shortcuts, especially for B2B buyers.',
                        'action' => 'Add a "Trusted by" logo strip beneath the hero.',
                        'guidance' => 'Use real customers only, with permission. Grayscale logos keep visual hierarchy intact.',
                    ];
                }),

            self::r('TR-003', 'trust', 'No contact information visible', 'high', 4, 3, 2, 4,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['contact']) return null;
                    return [
                        'evidence' => 'No phone number, email address, or physical address found in the page text.',
                        'explanation' => 'Visitors who cannot find a way to reach a human assume there is none. This is a common abandonment trigger for considered purchases.',
                        'action' => 'Expose a contact route in the header or footer.',
                        'guidance' => 'At minimum an email and a contact page link; add a phone number for high-ticket offers.',
                    ];
                }),

            self::r('TR-004', 'trust', 'No privacy policy link', 'high', 4, 3, 1, 5,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['privacy']) return null;
                    return [
                        'evidence' => 'No link to a privacy policy was found.',
                        'explanation' => 'A privacy policy is a legal requirement in most jurisdictions and a trust signal at the point of data entry.',
                        'action' => 'Add a privacy policy link in the footer and beside every form.',
                        'guidance' => 'Link text should say "Privacy Policy", not "Legal".',
                    ];
                }),

            self::r('TR-005', 'trust', 'No security or guarantee indicators', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['security']) return null;
                    return [
                        'evidence' => 'No security badge, guarantee, or certification language detected.',
                        'explanation' => 'Risk-reversal statements (money-back guarantee, SOC 2, encryption) lower the perceived cost of trying.',
                        'action' => 'Add a risk-reversal statement near the primary CTA.',
                        'guidance' => 'Only claim certifications you actually hold.',
                    ];
                }),

            self::r('TR-006', 'trust', 'No case studies or results', 'medium', 3, 3, 4, 3,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['case_studies']) return null;
                    return [
                        'evidence' => 'No case study, results, or "success story" content detected.',
                        'explanation' => 'Case studies answer "does this work for someone like me?" — the question testimonials alone do not settle.',
                        'action' => 'Publish at least one case study with a before/after metric.',
                        'guidance' => 'Structure: problem → approach → measured result → quote.',
                    ];
                }),

            self::r('TR-007', 'trust', 'Testimonials lack attribution', 'medium', 3, 3, 1, 3,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['testimonials'] === 0) return null;
                    if ($t['attributed_testimonials'] > 0) return null;
                    return [
                        'evidence' => "{$t['testimonials']} testimonial block(s) found, none with a name or company attribution.",
                        'explanation' => 'Unattributed quotes read as marketing copy rather than real customer voices.',
                        'action' => 'Add name, role and company to each testimonial.',
                        'guidance' => 'A photo and a company logo increase credibility further.',
                    ];
                }),

            self::r('TR-008', 'trust', 'No awards or certifications', 'low', 2, 2, 3, 2,
                function (PageContext $p) {
                    $t = TrustAnalyzer::analyze($p);
                    if ($t['awards']) return null;
                    return [
                        'evidence' => 'No award, rating, or certification mention detected.',
                        'explanation' => 'Third-party recognition is a low-cost credibility signal, though its impact varies by market.',
                        'action' => 'Display relevant awards or ratings if you hold them.',
                        'guidance' => 'Skip this if you have none — never fabricate recognition.',
                    ];
                }),

            self::r('TR-009', 'trust', 'No team or about information', 'low', 2, 2, 3, 2,
                function (PageContext $p) {
                    $text = strtolower($p->bodyText);
                    if ($text === '') return null;
                    foreach (['about us', 'our team', 'our story', 'founded', 'leadership'] as $s) {
                        if (str_contains($text, $s)) return null;
                    }
                    return [
                        'evidence' => 'No "about", "team", or company-story content detected.',
                        'explanation' => 'For newer brands, showing the humans behind the product reduces the "is this a real company?" doubt.',
                        'action' => 'Add a short about section or link.',
                        'guidance' => 'Two sentences plus a team photo is enough.',
                    ];
                }),

            self::r('TR-010', 'trust', 'No FAQ addressing objections', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $text = strtolower($p->bodyText);
                    if (str_contains($text, 'faq') || str_contains($text, 'frequently asked')) return null;
                    return [
                        'evidence' => 'No FAQ section detected.',
                        'explanation' => 'An FAQ pre-empts the objections that stop a visitor from converting, without requiring them to contact you.',
                        'action' => 'Add an FAQ covering pricing, setup time, cancellation and data handling.',
                        'guidance' => 'Use real questions from sales calls and support tickets.',
                    ];
                }),
        ];
    }

    // =====================================================================
    // FORMS (part of UX category)
    // =====================================================================
    private static function forms(): array
    {
        return [
            self::r('FRM-001', 'ux', 'Form has too many fields', 'high', 4, 4, 3, 4,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['max_fields'] <= 6) return null;
                    return [
                        'evidence' => "The largest form has {$f['max_fields']} input fields.",
                        'explanation' => 'Each additional field adds friction and abandonment. Forms above ~6 fields see materially lower completion in most published benchmarks.',
                        'action' => 'Remove every field not needed to complete the immediate next step.',
                        'guidance' => 'Ask for email only at first touch; collect the rest after the account exists.',
                    ];
                }),

            self::r('FRM-002', 'ux', 'Form fields lack labels', 'high', 4, 3, 2, 4,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['unlabeled_fields'] === 0) return null;
                    return [
                        'evidence' => "{$f['unlabeled_fields']} of {$f['total_fields']} fields have no associated <label>.",
                        'explanation' => 'Placeholder-only fields disappear once typing starts, and screen readers cannot announce them. Both hurt completion and accessibility.',
                        'action' => 'Add a visible <label for="..."> to every field.',
                        'guidance' => 'Keep placeholders as examples, not as the label.',
                    ];
                }),

            self::r('FRM-003', 'ux', 'Form has no submit button', 'critical', 5, 5, 1, 4,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['forms_without_submit'] === 0) return null;
                    return [
                        'evidence' => "{$f['forms_without_submit']} form(s) contain no submit button.",
                        'explanation' => 'Without a submit control the form cannot be completed by keyboard or by visitors who do not press Enter.',
                        'action' => 'Add an explicit submit button to every form.',
                        'guidance' => 'Label it with the outcome: "Get my audit", not "Submit".',
                    ];
                }),

            self::r('FRM-004', 'ux', 'No privacy reassurance near form', 'medium', 3, 3, 1, 4,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['has_privacy_note']) return null;
                    return [
                        'evidence' => 'No privacy or "we will not share your data" note found near the form.',
                        'explanation' => 'A short reassurance beside the submit button reduces hesitation at the moment of highest friction.',
                        'action' => 'Add one line under the form: "We never share your email."',
                        'guidance' => 'Link the privacy policy from the same line.',
                    ];
                }),

            self::r('FRM-005', 'ux', 'No validation hints or required markers', 'medium', 3, 2, 2, 3,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['required_fields'] > 0 || $f['has_hints']) return null;
                    return [
                        'evidence' => 'No required-field markers or format hints detected in the form.',
                        'explanation' => 'Visitors cannot tell which fields are mandatory until submission fails, which causes avoidable errors.',
                        'action' => 'Mark required fields and add format hints where relevant.',
                        'guidance' => 'Use "required" plus a visible asterisk; add "e.g. name@company.com" to email fields.',
                    ];
                }),

            self::r('FRM-006', 'ux', 'Form uses GET method for sensitive data', 'high', 4, 3, 1, 3,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['get_forms'] === 0) return null;
                    return [
                        'evidence' => "{$f['get_forms']} form(s) use method=\"GET\".",
                        'explanation' => 'GET submits field values in the URL, exposing them in browser history, logs and referrer headers.',
                        'action' => 'Switch data-collecting forms to POST.',
                        'guidance' => 'Use POST for anything containing personal data.',
                    ];
                }),

            self::r('FRM-007', 'ux', 'No autocomplete attributes', 'low', 2, 2, 1, 3,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['autocomplete_fields'] > 0) return null;
                    return [
                        'evidence' => 'No autocomplete attributes found on form fields.',
                        'explanation' => 'Autocomplete lets browsers fill known values, measurably reducing typing effort on mobile.',
                        'action' => 'Add autocomplete="email", "name", "tel" etc. to the relevant fields.',
                        'guidance' => 'Follow the HTML autofill token list.',
                    ];
                }),

            self::r('FRM-008', 'ux', 'No multi-step or progressive disclosure on long form', 'medium', 2, 3, 4, 3,
                function (PageContext $p) {
                    $f = FormAnalyzer::analyze($p);
                    if ($f['forms'] === 0) return null;
                    if ($f['max_fields'] < 8) return null;
                    return [
                        'evidence' => "A single-step form with {$f['max_fields']} fields was detected.",
                        'explanation' => 'Long single-step forms look intimidating before the visitor starts. Splitting them into steps increases completion in many tests.',
                        'action' => 'Split the form into 2–3 short steps with a progress indicator.',
                        'guidance' => 'Ask for the lowest-friction field first.',
                    ];
                }),
        ];
    }

    // =====================================================================
    // UX (15%)
    // =====================================================================
    private static function ux(): array
    {
        return [
            self::r('UX-001', 'ux', 'No clear visual hierarchy in headings', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $levels = array_unique(array_column($p->headings, 'level'));
                    sort($levels);
                    if (count($levels) >= 2) return null;
                    if ($p->headings === []) return null;
                    return [
                        'evidence' => 'Only heading level(s) '.implode(', ', $levels).' used across the page.',
                        'explanation' => 'A flat heading structure makes long pages hard to scan and breaks the document outline for assistive tech.',
                        'action' => 'Introduce H2 section headings and H3 sub-points.',
                        'guidance' => 'One H1, several H2s, H3s only under an H2.',
                    ];
                }),

            self::r('UX-002', 'ux', 'Heading levels skip', 'low', 3, 2, 1, 3,
                function (PageContext $p) {
                    $prev = 0;
                    foreach ($p->headings as $h) {
                        if ($prev > 0 && $h['level'] > $prev + 1) {
                            return [
                                'evidence' => "Heading jumps from H{$prev} to H{$h['level']} (\"".mb_substr($h['text'], 0, 60).'").',
                                'explanation' => 'Skipped levels break the semantic outline and confuse screen-reader navigation.',
                                'action' => 'Use heading levels in order without skipping.',
                                'guidance' => 'Style with CSS rather than choosing a level for its size.',
                            ];
                        }
                        $prev = $h['level'];
                    }
                    return null;
                }),

            self::r('UX-003', 'ux', 'Very little content on page', 'medium', 3, 3, 3, 3,
                function (PageContext $p) {
                    if ($p->wordCount === 0) return null;
                    if ($p->wordCount >= 120) return null;
                    return [
                        'evidence' => "Page body contains approximately {$p->wordCount} words.",
                        'explanation' => 'Thin pages rarely answer the questions a visitor needs before converting, and give search engines little to rank.',
                        'action' => 'Expand the page with objection-handling content.',
                        'guidance' => 'Add how-it-works, proof, and FAQ sections.',
                    ];
                }),

            self::r('UX-004', 'ux', 'Excessive content density', 'low', 2, 2, 3, 2,
                function (PageContext $p) {
                    if ($p->wordCount < 2500) return null;
                    return [
                        'evidence' => "Page body contains approximately {$p->wordCount} words.",
                        'explanation' => 'Very long pages without clear sectioning cause visitors to skim past the CTA.',
                        'action' => 'Break the page into scannable sections with subheads and repeated CTAs.',
                        'guidance' => 'Aim for one idea per section, with a CTA every 2–3 sections.',
                    ];
                }),

            self::r('UX-005', 'ux', 'No images or visual content', 'medium', 3, 3, 3, 3,
                function (PageContext $p) {
                    if ($p->images !== []) return null;
                    return [
                        'evidence' => 'No <img> elements found on the page.',
                        'explanation' => 'Product visuals or human imagery help visitors understand the offer faster than text alone.',
                        'action' => 'Add at least one relevant product or outcome visual.',
                        'guidance' => 'Prefer real product screenshots over stock photography.',
                    ];
                }),

            self::r('UX-006', 'ux', 'Images missing alt text', 'medium', 4, 2, 1, 4,
                function (PageContext $p) {
                    if ($p->images === []) return null;
                    $missing = array_filter($p->images, fn ($i) => ! $i['has_alt']);
                    if ($missing === []) return null;
                    return [
                        'evidence' => count($missing).' of '.count($p->images).' images have no alt attribute.',
                        'explanation' => 'Missing alt text harms accessibility and loses image-search traffic.',
                        'action' => 'Add descriptive alt text to every meaningful image.',
                        'guidance' => 'Use alt="" only for purely decorative images.',
                    ];
                }),

            self::r('UX-007', 'ux', 'No clear next step at page end', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $tail = strtolower(mb_substr($p->bodyText, -1200));
                    if ($tail === '') return null;
                    $ctas = CtaAnalyzer::detect($p);
                    foreach ($ctas as $c) {
                        $t = strtolower(trim($c['text']));
                        if ($t !== '' && str_contains($tail, $t)) return null;
                    }
                    return [
                        'evidence' => 'No CTA label appears in the final ~1200 characters of body text.',
                        'explanation' => 'Visitors who read to the end are the most qualified. Ending without a CTA wastes that intent.',
                        'action' => 'Close the page with a repeated primary CTA.',
                        'guidance' => 'Add a short closing section: one line of value + the primary button.',
                    ];
                }),

            self::r('UX-008', 'ux', 'No breadcrumb or orientation cue', 'low', 2, 2, 2, 2,
                function (PageContext $p) {
                    if ($p->has('nav[aria-label*="readcrumb"], .breadcrumb, .breadcrumbs')) return null;
                    return [
                        'evidence' => 'No breadcrumb navigation detected.',
                        'explanation' => 'On deeper pages, breadcrumbs help visitors understand where they are and how to get back.',
                        'action' => 'Add breadcrumbs on all pages below the top level.',
                        'guidance' => 'Mark up with schema.org BreadcrumbList for search benefit.',
                    ];
                }),

            self::r('UX-009', 'ux', 'No skip-to-content link', 'low', 3, 1, 1, 3,
                function (PageContext $p) {
                    if ($p->has('a[href^="#main"], a[href^="#content"], .skip-link, .skip-to-content')) return null;
                    return [
                        'evidence' => 'No skip-to-content link found.',
                        'explanation' => 'Keyboard and screen-reader users must tab through the whole navigation on every page without it.',
                        'action' => 'Add a visually-hidden skip link as the first focusable element.',
                        'guidance' => 'Reveal it on focus with CSS.',
                    ];
                }),

            self::r('UX-010', 'ux', 'No clear page purpose in first heading', 'medium', 3, 3, 2, 4,
                function (PageContext $p) {
                    $h = $p->h1();
                    if ($h === null) return null;
                    if (str_word_count($h) >= 3) return null;
                    return [
                        'evidence' => "H1 is only ".str_word_count($h)." word(s): \"{$h}\".",
                        'explanation' => 'A one- or two-word headline does not tell a new visitor what the page is for.',
                        'action' => 'Expand the H1 into a complete promise.',
                        'guidance' => 'Aim for 6–12 words naming the outcome and audience.',
                    ];
                }),
        ];
    }

    // =====================================================================
    // NAVIGATION
    // =====================================================================
    private static function navigation(): array
    {
        return [
            self::r('NAV-001', 'ux', 'No navigation menu', 'high', 4, 3, 2, 5,
                function (PageContext $p) {
                    if ($p->has('nav, header nav, [role="navigation"]')) return null;
                    return [
                        'evidence' => 'No <nav> or role="navigation" element found.',
                        'explanation' => 'Without navigation, visitors cannot explore the site and must use the back button to leave.',
                        'action' => 'Add a persistent top navigation with the 4–6 most important destinations.',
                        'guidance' => 'Include the primary CTA in the nav bar as a button.',
                    ];
                }),

            self::r('NAV-002', 'ux', 'Navigation has too many top-level items', 'medium', 3, 2, 2, 3,
                function (PageContext $p) {
                    $n = $p->count('nav > ul > li, header nav > ul > li');
                    if ($n <= 7 || $n === 0) return null;
                    return [
                        'evidence' => "Top-level navigation contains {$n} items.",
                        'explanation' => 'More than ~7 top-level items increases scanning cost and dilutes attention.',
                        'action' => 'Group secondary items under a dropdown or move them to the footer.',
                        'guidance' => 'Keep 4–6 top-level items.',
                    ];
                }),

            self::r('NAV-003', 'ux', 'No footer', 'medium', 3, 2, 2, 4,
                function (PageContext $p) {
                    if ($p->has('footer')) return null;
                    return [
                        'evidence' => 'No <footer> element found.',
                        'explanation' => 'The footer carries trust and legal links (privacy, terms, contact) that visitors look for before converting.',
                        'action' => 'Add a footer with contact, legal and key navigation links.',
                        'guidance' => 'Group into columns: Product, Company, Legal.',
                    ];
                }),

            self::r('NAV-004', 'ux', 'No logo link to home', 'low', 3, 1, 1, 3,
                function (PageContext $p) {
                    if ($p->has('header a[href="/"], header a[href$="' . parse_url($p->url, PHP_URL_HOST) . '"], .logo a, a.logo')) return null;
                    return [
                        'evidence' => 'No logo link back to the homepage detected in the header.',
                        'explanation' => 'The logo-as-home-link is a universal convention; its absence makes the site feel unfamiliar.',
                        'action' => 'Wrap the header logo in a link to "/".',
                        'guidance' => 'Add aria-label="Home" to the link.',
                    ];
                }),

            self::r('NAV-005', 'ux', 'No search or wayfinding on large site', 'low', 2, 2, 3, 2,
                function (PageContext $p) {
                    if ($p->has('input[type="search"], form[role="search"], .search')) return null;
                    return [
                        'evidence' => 'No site search input detected.',
                        'explanation' => 'On content-heavy sites, visitors who cannot find what they want leave rather than browse.',
                        'action' => 'Add site search if the site has more than ~20 pages.',
                        'guidance' => 'Skip this for small marketing sites.',
                    ];
                }),
        ];
    }

    // =====================================================================
    // MOBILE (15%)
    // =====================================================================
    private static function mobile(): array
    {
        return [
            self::r('MOB-001', 'mobile', 'Missing viewport meta tag', 'critical', 5, 5, 1, 5,
                function (PageContext $p) {
                    if ($p->hasViewport()) return null;
                    return [
                        'evidence' => 'No <meta name="viewport"> tag found in <head>.',
                        'explanation' => 'Without a viewport tag, mobile browsers render the desktop layout zoomed out, making text unreadable and buttons untappable. This is the single most damaging mobile defect.',
                        'action' => 'Add <meta name="viewport" content="width=device-width, initial-scale=1">.',
                        'guidance' => 'Do not disable user scaling (avoid user-scalable=no).',
                    ];
                }),

            self::r('MOB-002', 'mobile', 'Fixed-width layout indicators', 'high', 3, 4, 3, 4,
                function (PageContext $p) {
                    if (preg_match('/width\s*:\s*(9[0-9]{2}|1[0-9]{3})px/i', $p->html)) {
                        return [
                            'evidence' => 'Inline or embedded CSS contains a fixed pixel width of 900px or more.',
                            'explanation' => 'Fixed-width containers force horizontal scrolling on phones, which is a strong abandonment signal.',
                            'action' => 'Replace fixed widths with max-width and percentage-based layouts.',
                            'guidance' => 'Use max-width: 100% and CSS grid/flex.',
                        ];
                    }
                    return null;
                }, 'Detected from inline CSS only; external stylesheets were not parsed.'),

            self::r('MOB-003', 'mobile', 'No responsive framework or media queries', 'medium', 3, 3, 3, 4,
                function (PageContext $p) {
                    if (str_contains($p->html, '@media')) return null;
                    if (preg_match('/bootstrap|tailwind|foundation|bulma/i', $p->html)) return null;
                    return [
                        'evidence' => 'No @media queries or recognised responsive framework found in the HTML.',
                        'explanation' => 'Without responsive rules the layout cannot adapt to small screens.',
                        'action' => 'Add responsive breakpoints for mobile and tablet.',
                        'guidance' => 'At minimum handle widths below 768px and 480px.',
                    ];
                }, 'External stylesheets were not fetched, so media queries defined there are not visible to this check.'),

            self::r('MOB-004', 'mobile', 'Small tap targets indicated', 'medium', 2, 3, 2, 3,
                function (PageContext $p) {
                    if (preg_match('/(font-size\s*:\s*(1[0-1]|[0-9])px)/i', $p->html)) {
                        return [
                            'evidence' => 'CSS declares font sizes of 11px or smaller.',
                            'explanation' => 'Text below ~12px is hard to read on phones and usually accompanies undersized tap targets.',
                            'action' => 'Raise body text to at least 16px and tap targets to 44×44px.',
                            'guidance' => '16px body text also prevents iOS input zoom.',
                        ];
                    }
                    return null;
                }, 'Inferred from inline CSS font sizes; rendered tap-target size was not measured.'),

            self::r('MOB-005', 'mobile', 'No mobile-friendly navigation pattern', 'low', 2, 2, 3, 3,
                function (PageContext $p) {
                    if ($p->has('.hamburger, .menu-toggle, [aria-label*="menu"], button.navbar-toggler')) return null;
                    if ($p->count('nav > ul > li') <= 5) return null;
                    return [
                        'evidence' => 'Navigation has more than 5 top-level items and no hamburger/menu-toggle control.',
                        'explanation' => 'Wide navigation without a collapse control overflows on small screens.',
                        'action' => 'Collapse navigation into a hamburger menu below the tablet breakpoint.',
                        'guidance' => 'Ensure the toggle has an accessible name and 44px target.',
                    ];
                }),
        ];
    }

    // =====================================================================
    // CONTENT CLARITY (10%)
    // =====================================================================
    private static function content(): array
    {
        return [
            self::r('CNT-001', 'content', 'Missing meta description', 'medium', 4, 2, 1, 4,
                function (PageContext $p) {
                    if ($p->metaDescription() !== null) return null;
                    return [
                        'evidence' => 'No <meta name="description"> content found.',
                        'explanation' => 'The meta description is the snippet shown in search results; without it, search engines invent one, often poorly.',
                        'action' => 'Write a 140–160 character description with the main benefit and a CTA.',
                        'guidance' => 'Unique per page; include the primary keyword naturally.',
                    ];
                }),

            self::r('CNT-002', 'content', 'Meta description length out of range', 'low', 3, 1, 1, 3,
                function (PageContext $p) {
                    $d = $p->metaDescription();
                    if ($d === null) return null;
                    $len = mb_strlen($d);
                    if ($len >= 70 && $len <= 165) return null;
                    return [
                        'evidence' => "Meta description is {$len} characters.",
                        'explanation' => 'Descriptions under ~70 characters waste snippet space; over ~165 are truncated.',
                        'action' => 'Rewrite to 140–160 characters.',
                        'guidance' => 'Front-load the benefit; end with a soft CTA.',
                    ];
                }),

            self::r('CNT-003', 'content', 'Title tag missing', 'high', 5, 3, 1, 4,
                function (PageContext $p) {
                    if ($p->title() !== null) return null;
                    return [
                        'evidence' => 'No <title> element content found.',
                        'explanation' => 'The title is the clickable headline in search results and the browser tab label.',
                        'action' => 'Add a unique, descriptive <title>.',
                        'guidance' => 'Format: "Primary benefit | Brand", 50–60 characters.',
                    ];
                }),

            self::r('CNT-004', 'content', 'Title tag length out of range', 'low', 3, 1, 1, 3,
                function (PageContext $p) {
                    $t = $p->title();
                    if ($t === null) return null;
                    $len = mb_strlen($t);
                    if ($len >= 20 && $len <= 65) return null;
                    return [
                        'evidence' => "Title is {$len} characters: \"{$t}\".",
                        'explanation' => 'Titles over ~65 characters are truncated in search results; very short titles waste ranking opportunity.',
                        'action' => 'Rewrite the title to 50–60 characters.',
                        'guidance' => 'Lead with the benefit, end with the brand.',
                    ];
                }),

            self::r('CNT-005', 'content', 'No paragraph structure', 'low', 2, 2, 2, 3,
                function (PageContext $p) {
                    if ($p->count('p') >= 3) return null;
                    if ($p->wordCount < 100) return null;
                    return [
                        'evidence' => 'Fewer than 3 <p> elements on a page with '.$p->wordCount.' words.',
                        'explanation' => 'Long unbroken text blocks are hard to scan; visitors skip them.',
                        'action' => 'Break copy into short paragraphs of 2–3 sentences.',
                        'guidance' => 'One idea per paragraph.',
                    ];
                }),

            self::r('CNT-006', 'content', 'No bulleted or numbered lists', 'low', 2, 2, 1, 3,
                function (PageContext $p) {
                    if ($p->count('ul li, ol li') > 0) return null;
                    if ($p->wordCount < 150) return null;
                    return [
                        'evidence' => 'No list elements found on a page with '.$p->wordCount.' words.',
                        'explanation' => 'Lists make benefits and features scannable, which is how most visitors read marketing pages.',
                        'action' => 'Convert feature and benefit copy into bullet lists.',
                        'guidance' => '3–6 bullets per list, each under 12 words.',
                    ];
                }),

            self::r('CNT-007', 'content', 'No internal links', 'medium', 3, 2, 2, 3,
                function (PageContext $p) {
                    $internal = array_filter($p->links, function ($l) use ($p) {
                        $h = $l['href'] ?? '';
                        return $h && ! str_starts_with($h, 'http') && ! str_starts_with($h, '#');
                    });
                    if ($internal !== []) return null;
                    return [
                        'evidence' => 'No relative internal links found on the page.',
                        'explanation' => 'Internal links guide visitors deeper into the funnel and distribute search authority.',
                        'action' => 'Link to related product, pricing and proof pages.',
                        'guidance' => 'Use descriptive anchor text, not "click here".',
                    ];
                }),

            self::r('CNT-008', 'content', 'No clear language declaration', 'low', 3, 1, 1, 2,
                function (PageContext $p) {
                    if ($p->lang() !== null && $p->lang() !== '') return null;
                    return [
                        'evidence' => 'The <html> element has no lang attribute.',
                        'explanation' => 'Screen readers use the lang attribute to choose the correct pronunciation.',
                        'action' => 'Add lang="en" (or the correct language) to <html>.',
                        'guidance' => 'Use BCP-47 language tags.',
                    ];
                }),

            self::r('CNT-009', 'content', 'No structured data', 'low', 2, 2, 3, 2,
                function (PageContext $p) {
                    if ($p->hasStructuredData()) return null;
                    return [
                        'evidence' => 'No JSON-LD structured data found.',
                        'explanation' => 'Structured data enables rich results (ratings, FAQs, breadcrumbs) that improve click-through from search.',
                        'action' => 'Add JSON-LD for Organization and the relevant page type.',
                        'guidance' => 'Validate with Google\'s Rich Results Test.',
                    ];
                }),

            self::r('CNT-010', 'content', 'No social sharing metadata', 'low', 2, 1, 1, 2,
                function (PageContext $p) {
                    if ($p->hasOpenGraph()) return null;
                    return [
                        'evidence' => 'No Open Graph meta tags found.',
                        'explanation' => 'Without OG tags, shared links render without a title, description or image, reducing click-through.',
                        'action' => 'Add og:title, og:description, og:image and og:url.',
                        'guidance' => 'Image should be 1200×630.',
                    ];
                }),
        ];
    }

    // =====================================================================
    // TECHNICAL READINESS (5%)
    // =====================================================================
    private static function technical(): array
    {
        return [
            self::r('TEC-001', 'technical', 'Page returned an error status', 'critical', 5, 5, 3, 5,
                function (PageContext $p) {
                    if ($p->statusCode < 400) return null;
                    return [
                        'evidence' => "Page returned HTTP {$p->statusCode}.",
                        'explanation' => 'An error page cannot convert. Visitors and search engines both treat it as a dead end.',
                        'action' => 'Fix the server error or redirect the URL to a working page.',
                        'guidance' => 'Use a 301 to the closest equivalent page if the content moved.',
                    ];
                }),

            self::r('TEC-002', 'technical', 'No canonical URL', 'medium', 3, 2, 1, 3,
                function (PageContext $p) {
                    if ($p->canonical() !== null) return null;
                    return [
                        'evidence' => 'No <link rel="canonical"> found.',
                        'explanation' => 'Without a canonical, duplicate URLs (with tracking parameters, www vs non-www) split ranking signals.',
                        'action' => 'Add a self-referencing canonical to every page.',
                        'guidance' => 'Use the absolute, preferred URL.',
                    ];
                }),

            self::r('TEC-003', 'technical', 'No favicon', 'low', 3, 1, 1, 2,
                function (PageContext $p) {
                    if ($p->hasFavicon()) return null;
                    return [
                        'evidence' => 'No favicon link found in <head>.',
                        'explanation' => 'A missing favicon makes the tab look unfinished and slightly reduces perceived legitimacy.',
                        'action' => 'Add a favicon and apple-touch-icon.',
                        'guidance' => 'Provide at least 32×32 and 180×180 PNG.',
                    ];
                }),

            self::r('TEC-004', 'technical', 'No HTTPS indicators', 'high', 4, 4, 2, 5,
                function (PageContext $p) {
                    if (str_starts_with(strtolower($p->url), 'https://')) return null;
                    return [
                        'evidence' => 'The scanned URL uses http:// rather than https://.',
                        'explanation' => 'Browsers mark HTTP pages as "Not secure", which is a direct trust and conversion penalty.',
                        'action' => 'Serve the site over HTTPS and redirect all HTTP traffic.',
                        'guidance' => 'Use a free certificate (Let\'s Encrypt) and HSTS.',
                    ];
                }),

            self::r('TEC-005', 'technical', 'No robots meta or sitemap reference', 'low', 2, 1, 1, 2,
                function (PageContext $p) {
                    if ($p->has('meta[name="robots"]') || $p->has('link[rel="sitemap"]')) return null;
                    return [
                        'evidence' => 'No robots meta tag or sitemap link found.',
                        'explanation' => 'Explicit crawl directives help search engines index the right pages.',
                        'action' => 'Add a robots meta tag and reference the sitemap.',
                        'guidance' => 'Use "index,follow" for pages you want ranked.',
                    ];
                }),

            self::r('TEC-006', 'technical', 'No hreflang for multilingual site', 'low', 2, 1, 3, 2,
                function (PageContext $p) {
                    if ($p->has('link[hreflang]')) return null;
                    if (! preg_match('/\/(de|fr|es|it|pt|nl|ja|zh|ar|hi)\//i', parse_url($p->url, PHP_URL_PATH) ?? '')) return null;
                    return [
                        'evidence' => 'URL suggests a localised path but no hreflang tags were found.',
                        'explanation' => 'Without hreflang, search engines may serve the wrong language version.',
                        'action' => 'Add reciprocal hreflang tags for each locale.',
                        'guidance' => 'Include an x-default entry.',
                    ];
                }),

            self::r('TEC-007', 'technical', 'No 404 handling signal', 'low', 2, 1, 2, 2,
                function (PageContext $p) {
                    if ($p->statusCode !== 404) return null;
                    return [
                        'evidence' => 'Page returned HTTP 404.',
                        'explanation' => 'A 404 reached from an internal link means a broken path in the funnel.',
                        'action' => 'Fix or redirect the broken link.',
                        'guidance' => 'Add a helpful 404 page with navigation and search.',
                    ];
                }),

            self::r('TEC-008', 'technical', 'No compression or caching headers', 'low', 2, 2, 2, 3,
                function (PageContext $p) {
                    return null; // headers are not available in PageContext; handled by performance rules
                }, 'Response headers are not captured in this analysis pass.'),
        ];
    }

    // =====================================================================
    // PERFORMANCE & ACCESSIBILITY (measurable subset)
    // =====================================================================
    private static function performance(): array
    {
        return [
            self::r('PRF-001', 'technical', 'Slow server response', 'high', 4, 4, 3, 5,
                function (PageContext $p) {
                    if ($p->loadTimeMs === null) return null;
                    if ($p->loadTimeMs < 1500) return null;
                    return [
                        'evidence' => "Server response plus download took {$p->loadTimeMs} ms.",
                        'explanation' => 'Response times above ~1.5 s measurably increase bounce, especially on mobile networks.',
                        'action' => 'Investigate server response time and enable caching.',
                        'guidance' => 'Target under 800 ms TTFB. Add page caching and a CDN.',
                    ];
                }, 'Measured as total request time from this server, not real-user TTFB.'),

            self::r('PRF-002', 'technical', 'Very large HTML document', 'medium', 3, 3, 3, 4,
                function (PageContext $p) {
                    if ($p->htmlBytes < 500_000) return null;
                    $kb = round($p->htmlBytes / 1024);
                    return [
                        'evidence' => "HTML document is {$kb} KB.",
                        'explanation' => 'Large HTML delays first paint, particularly on slow mobile connections.',
                        'action' => 'Reduce inline data, split long pages, and move content behind pagination.',
                        'guidance' => 'Aim for under 100 KB of HTML.',
                    ];
                }),

            self::r('PRF-003', 'technical', 'Too many images on one page', 'low', 2, 2, 2, 3,
                function (PageContext $p) {
                    if (count($p->images) <= 25) return null;
                    return [
                        'evidence' => count($p->images).' <img> elements on the page.',
                        'explanation' => 'Many images increase page weight and slow rendering unless lazy-loaded.',
                        'action' => 'Lazy-load below-the-fold images and compress the rest.',
                        'guidance' => 'Use loading="lazy" and modern formats (WebP/AVIF).',
                    ];
                }),

            self::r('PRF-004', 'technical', 'No lazy loading on images', 'low', 2, 2, 1, 3,
                function (PageContext $p) {
                    if (count($p->images) < 8) return null;
                    if (str_contains($p->html, 'loading="lazy"')) return null;
                    return [
                        'evidence' => count($p->images).' images and no loading="lazy" attribute found.',
                        'explanation' => 'Eagerly loading every image delays the content the visitor actually sees first.',
                        'action' => 'Add loading="lazy" to below-the-fold images.',
                        'guidance' => 'Never lazy-load the hero image.',
                    ];
                }),

            self::r('PRF-005', 'technical', 'No width/height on images (layout shift)', 'medium', 3, 2, 1, 3,
                function (PageContext $p) {
                    if ($p->images === []) return null;
                    $noDims = 0;
                    foreach ($p->images as $img) {
                        if (empty($img['src'])) continue;
                        $noDims++;
                    }
                    if ($noDims === 0) return null;
                    if (preg_match('/<img[^>]+width=[^>]+height=/i', $p->html)) return null;
                    return [
                        'evidence' => 'Images are declared without width and height attributes.',
                        'explanation' => 'Without intrinsic dimensions the browser cannot reserve space, causing layout shift (CLS) as images load.',
                        'action' => 'Add width and height attributes to every image.',
                        'guidance' => 'Pair with CSS aspect-ratio for responsive sizing.',
                    ];
                }),

            self::r('PRF-006', 'technical', 'No font-display strategy', 'low', 2, 1, 1, 2,
                function (PageContext $p) {
                    if (! str_contains($p->html, 'fonts.googleapis') && ! str_contains($p->html, '@font-face')) return null;
                    if (str_contains($p->html, 'font-display')) return null;
                    return [
                        'evidence' => 'Web fonts are loaded without a font-display declaration.',
                        'explanation' => 'Without font-display, text can stay invisible until the font loads (FOIT).',
                        'action' => 'Add font-display: swap to @font-face rules.',
                        'guidance' => 'Preload the primary font file.',
                    ];
                }),

            self::r('PRF-007', 'technical', 'No ARIA landmarks', 'low', 3, 1, 2, 3,
                function (PageContext $p) {
                    if ($p->has('main, [role="main"]')) return null;
                    return [
                        'evidence' => 'No <main> element or role="main" landmark found.',
                        'explanation' => 'Landmarks let screen-reader users jump directly to the main content.',
                        'action' => 'Wrap the primary content in <main>.',
                        'guidance' => 'One <main> per page.',
                    ];
                }),

            self::r('PRF-008', 'technical', 'No form field autocomplete for accessibility', 'low', 2, 1, 1, 2,
                function (PageContext $p) {
                    if ($p->forms === []) return null;
                    return null; // covered by FRM-007
                }),

            self::r('PRF-009', 'technical', 'Inline styles block caching', 'low', 2, 1, 2, 2,
                function (PageContext $p) {
                    $inline = substr_count($p->html, 'style="');
                    if ($inline < 20) return null;
                    return [
                        'evidence' => "{$inline} inline style attributes found.",
                        'explanation' => 'Heavy inline styling prevents CSS caching across pages and bloats every response.',
                        'action' => 'Move repeated styles into a shared stylesheet.',
                        'guidance' => 'Keep only dynamic, per-element styles inline.',
                    ];
                }),

            self::r('PRF-010', 'technical', 'No preconnect for third-party origins', 'low', 2, 1, 1, 2,
                function (PageContext $p) {
                    if (! preg_match('/https?:\/\/(fonts|cdn|www\.google|analytics)/i', $p->html)) return null;
                    if (str_contains($p->html, 'preconnect')) return null;
                    return [
                        'evidence' => 'Third-party origins are referenced without preconnect hints.',
                        'explanation' => 'Preconnect lets the browser open connections early, reducing latency for fonts and analytics.',
                        'action' => 'Add <link rel="preconnect"> for each critical third-party origin.',
                        'guidance' => 'Limit to 2–3 origins to avoid connection overhead.',
                    ];
                }),
        ];
    }
}
