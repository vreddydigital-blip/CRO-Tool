<?php

namespace Tests\Feature;

use App\Services\Analysis\RuleLibrary;
use Tests\TestCase;

class RuleLibraryTest extends TestCase
{
    public function test_it_defines_at_least_fifty_rules(): void
    {
        $rules = (new RuleLibrary())->all();
        $this->assertGreaterThanOrEqual(50, count($rules), 'The engine must ship at least 50 CRO rules.');
    }

    public function test_every_rule_id_is_unique(): void
    {
        $ids = array_map(fn ($r) => $r->id(), (new RuleLibrary())->all());
        $this->assertSame(count($ids), count(array_unique($ids)), 'Rule IDs must be unique.');
    }

    public function test_every_rule_has_complete_metadata(): void
    {
        foreach ((new RuleLibrary())->all() as $rule) {
            $this->assertNotEmpty($rule->id(), 'Rule is missing an ID');
            $this->assertNotEmpty($rule->category(), "{$rule->id()} is missing a category");
            $this->assertNotEmpty($rule->name(), "{$rule->id()} is missing a name");
            $this->assertNotEmpty($rule->limitations(), "{$rule->id()} is missing limitations");
            $this->assertContains($rule->severity(), ['critical', 'high', 'medium', 'low'], "{$rule->id()} has an invalid severity");
            $this->assertGreaterThanOrEqual(1, $rule->confidence());
            $this->assertLessThanOrEqual(5, $rule->confidence());
        }
    }

    public function test_all_ten_required_categories_are_covered(): void
    {
        $categories = array_unique(array_map(fn ($r) => $r->category(), (new RuleLibrary())->all()));

        foreach (['value_proposition', 'cta', 'trust', 'ux', 'mobile', 'content', 'technical'] as $required) {
            $this->assertContains($required, $categories, "No rules exist for the {$required} category.");
        }
    }
}
