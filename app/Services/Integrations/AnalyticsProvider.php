<?php

namespace App\Services\Integrations;

/**
 * Contract for a future analytics integration.
 *
 * IMPORTANT: nothing in this application fabricates visitor or conversion
 * data. These interfaces exist so that a real GA4 / GTM / CRM connection can
 * be added later without changing the rest of the app. Until a provider is
 * actually connected and returning real data, every method here reports
 * "not connected" and the UI must say so.
 */
interface AnalyticsProvider
{
    /** Machine name, e.g. "ga4", "gtm", "hubspot". */
    public function key(): string;

    /** Human label for the UI. */
    public function label(): string;

    /** True only when real credentials are present AND verified. */
    public function isConnected(): bool;

    /**
     * Fetch real conversion events for a date range.
     *
     * @return array{ok: bool, events: array, error: ?string}
     */
    public function conversionEvents(string $projectUrl, string $from, string $to): array;

    /**
     * Fetch real visitor/session metrics for a date range.
     *
     * @return array{ok: bool, metrics: array, error: ?string}
     */
    public function visitorMetrics(string $projectUrl, string $from, string $to): array;
}
