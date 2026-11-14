<?php

namespace App\Services\Ai;

/**
 * Contract for an optional AI explanation layer.
 *
 * The product MUST work with no AI key configured. When no provider is
 * available, RuleBasedFallback is used and the UI says so.
 */
interface AiProvider
{
    public function isConfigured(): bool;

    /**
     * @param array $findings  structured findings (already validated, from our own DB)
     * @return array{ok:bool, text?:string, error?:string, provider?:string}
     */
    public function explain(array $findings, string $pageUrl): array;
}
