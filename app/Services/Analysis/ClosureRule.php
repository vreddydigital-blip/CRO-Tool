<?php

namespace App\Services\Analysis;

/**
 * A Rule whose behaviour is supplied as a closure.
 *
 * Using one class + closures keeps 60+ rules readable in a single file while
 * preserving the Rule contract (id, category, severity, confidence, evidence,
 * explanation, action, guidance, limitations).
 */
class ClosureRule extends Rule
{
    public function __construct(
        private string $id,
        private string $category,
        private string $name,
        private string $severity,
        private int $confidence,
        private int $impact,
        private int $effort,
        private int $reach,
        private \Closure $evaluator,
        private string $limitations = 'Based on server-rendered HTML only; JavaScript-injected content and visual layout were not evaluated.',
    ) {}

    public function id(): string { return $this->id; }
    public function category(): string { return $this->category; }
    public function name(): string { return $this->name; }
    public function severity(): string { return $this->severity; }
    public function confidence(): int { return $this->confidence; }
    public function impact(): int { return $this->impact; }
    public function effort(): int { return $this->effort; }
    public function reach(): int { return $this->reach; }
    public function limitations(): string { return $this->limitations; }

    public function evaluate(PageContext $page): ?array
    {
        return ($this->evaluator)($page);
    }
}
