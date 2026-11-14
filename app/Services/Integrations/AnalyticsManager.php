<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\Project;

/**
 * Resolves the analytics provider for a project.
 *
 * A real provider is only returned when the project has a stored, encrypted
 * integration row whose status is "connected". Otherwise the caller gets a
 * NotConnectedProvider, which reports no data instead of inventing any.
 */
class AnalyticsManager
{
    public function forProject(Project $project): AnalyticsProvider
    {
        $integration = Integration::where('project_id', $project->id)
            ->whereIn('provider', ['ga4', 'gtm', 'crm'])
            ->where('status', 'connected')
            ->first();

        if (! $integration) {
            return new NotConnectedProvider();
        }

        // A real driver would be constructed here from $integration->credentials
        // (stored encrypted). No driver ships yet, so we still report honestly.
        return new NotConnectedProvider($integration->provider, ucfirst($integration->provider).' (driver not implemented)');
    }

    /** Providers the UI can offer to connect. */
    public function available(): array
    {
        return [
            ['key' => 'ga4', 'label' => 'Google Analytics 4', 'status' => 'not_implemented'],
            ['key' => 'gtm', 'label' => 'Google Tag Manager', 'status' => 'not_implemented'],
            ['key' => 'crm', 'label' => 'CRM (HubSpot / Salesforce)', 'status' => 'not_implemented'],
        ];
    }
}
