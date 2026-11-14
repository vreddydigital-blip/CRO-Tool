<?php

namespace App\Services\Analysis;

/**
 * A single CRO rule.
 *
 * A rule is a pure function of a PageContext. It returns either null (the rule
 * passed, or the element it inspects is absent so no claim can be made) or a
 * Finding array. Rules must never assert something they did not observe.
 */
abstract class Rule
{
    /** Unique, stable identifier, e.g. "VP-001". */
    abstract public function id(): string;

    /** One of the category keys in ScoringEngine::WEIGHTS. */
    abstract public function category(): string;

    abstract public function name(): string;

    /** critical | high | medium | low */
    abstract public function severity(): string;

    /** 1-5, how sure we are the observation is real. */
    public function confidence(): int
    {
        return 3;
    }

    /** 1-5, expected effect on conversion if fixed. */
    public function impact(): int
    {
        return 3;
    }

    /** 1-5, how much of the site's traffic the page represents. */
    public function reach(): int
    {
        return 3;
    }

    /** 1-5, implementation cost. */
    public function effort(): int
    {
        return 3;
    }

    /** What we could not check, stated honestly. */
    public function limitations(): string
    {
        return 'Based on server-rendered HTML only; JavaScript-injected content and visual layout were not evaluated.';
    }

    /**
     * @return array{evidence:string, explanation:string, action:string, guidance:string}|null
     */
    abstract public function evaluate(PageContext $page): ?array;

    /** Convenience: build the finding payload. */
    public function finding(PageContext $page, array $result): array
    {
        return [
            'rule_id' => $this->id(),
            'category' => $this->category(),
            'rule_name' => $this->name(),
            'severity' => $this->severity(),
            'confidence' => $this->confidence(),
            'impact' => $this->impact(),
            'effort' => $this->effort(),
            'reach' => $this->reach(),
            'evidence' => $result['evidence'],
            'explanation' => $result['explanation'],
            'recommended_action' => $result['action'],
            'implementation_guidance' => $result['guidance'],
            'limitations' => $this->limitations(),
        ];
    }
}
