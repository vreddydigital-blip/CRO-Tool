<?php

namespace App\Services\Ai;

/**
 * Chooses the AI provider, falling back to rule-based output.
 * The app is fully functional with no AI key.
 */
class AiManager
{
    public function provider(): AiProvider
    {
        $key = config('services.ai.key');
        $base = config('services.ai.base_url');
        $model = config('services.ai.model');

        if ($key && $base && $model) {
            return new OpenAiCompatibleProvider($key, $base, $model);
        }

        return new RuleBasedFallback();
    }

    public function explain(array $findings, string $pageUrl): array
    {
        $primary = $this->provider();
        $result = $primary->explain($findings, $pageUrl);

        if (! ($result['ok'] ?? false)) {
            $fallback = new RuleBasedFallback();
            $r = $fallback->explain($findings, $pageUrl);
            $r['fallback_reason'] = $result['error'] ?? 'AI unavailable';
            return $r;
        }

        return $result;
    }
}
