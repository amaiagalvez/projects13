<?php

namespace Projects13\Tests\Unit\Validation;

use Projects13\Tests\TestCase;
use Illuminate\Validation\Rules\Unique;
use Projects13\Http\Requests\ProjectRequest;

class ProjectRequestTest extends TestCase
{
    public function test_name_rule_contains_unique_validation(): void
    {
        $request = new ProjectRequest;
        $rules = $request->rules();

        $nameRules = $rules['name'];
        $hasUniqueRule = array_filter($nameRules, static fn (mixed $rule): bool => $rule instanceof Unique);
        $this->assertNotEmpty($hasUniqueRule, 'name rule must contain unique validation');
    }

    public function test_authorize_returns_false_when_user_cannot_create(): void
    {
        $request = ProjectRequest::create('/', 'POST');
        $request->setUserResolver(fn () => null);
        $this->assertFalse($request->authorize());
    }
}
