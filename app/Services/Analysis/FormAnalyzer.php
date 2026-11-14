<?php

namespace App\Services\Analysis;

/**
 * Form friction analysis.
 *
 * Counts fields, labels, required markers, submit controls and privacy
 * reassurance. Reports only what is present in the DOM.
 */
class FormAnalyzer
{
    public static function analyze(PageContext $page): array
    {
        $forms = $page->forms;
        $totalFields = 0;
        $unlabeled = 0;
        $required = 0;
        $autocomplete = 0;
        $maxFields = 0;
        $withoutSubmit = 0;
        $getForms = 0;
        $hasHints = false;

        foreach ($forms as $f) {
            $count = count($f['fields']);
            $totalFields += $count;
            $maxFields = max($maxFields, $count);

            if (! $f['has_submit']) {
                $withoutSubmit++;
            }
            if ($f['method'] === 'GET') {
                $getForms++;
            }

            foreach ($f['fields'] as $field) {
                if ($field['label'] === null || trim((string) $field['label']) === '') {
                    $unlabeled++;
                }
                if ($field['required']) {
                    $required++;
                }
                if (! empty($field['autocomplete'])) {
                    $autocomplete++;
                }
                if (! empty($field['placeholder'])) {
                    $hasHints = true;
                }
            }
        }

        $text = strtolower($page->bodyText);
        $hasPrivacy = str_contains($text, 'privacy')
            || str_contains($text, 'we will never share')
            || str_contains($text, 'we never share')
            || str_contains($text, 'no spam')
            || str_contains($text, 'your data');

        return [
            'forms' => count($forms),
            'total_fields' => $totalFields,
            'max_fields' => $maxFields,
            'unlabeled_fields' => $unlabeled,
            'required_fields' => $required,
            'autocomplete_fields' => $autocomplete,
            'forms_without_submit' => $withoutSubmit,
            'get_forms' => $getForms,
            'has_hints' => $hasHints,
            'has_privacy_note' => $hasPrivacy,
        ];
    }
}
