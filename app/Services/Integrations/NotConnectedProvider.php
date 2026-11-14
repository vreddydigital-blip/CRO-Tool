<?php

namespace App\Services\Integrations;

/**
 * Placeholder provider used until a real analytics connection exists.
 *
 * It never returns numbers. Every call reports not-connected so the UI can
 * state plainly that no analytics data is available, rather than showing a
 * zero that a reader might mistake for a real measurement.
 */
class NotConnectedProvider implements AnalyticsProvider
{
    public function __construct(private string $key = 'none', private string $label = 'Not connected')
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isConnected(): bool
    {
        return false;
    }

    public function conversionEvents(string $projectUrl, string $from, string $to): array
    {
        return [
            'ok' => false,
            'events' => [],
            'error' => 'No analytics provider is connected. Connect GA4, GTM or a CRM to read real conversion events.',
        ];
    }

    public function visitorMetrics(string $projectUrl, string $from, string $to): array
    {
        return [
            'ok' => false,
            'metrics' => [],
            'error' => 'No analytics provider is connected. Visitor and session data is not available.',
        ];
    }
}
