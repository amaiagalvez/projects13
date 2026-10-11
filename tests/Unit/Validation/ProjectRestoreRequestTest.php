<?php

namespace Projects13\Tests\Unit\Validation;

use Projects13\Tests\TestCase;
use Projects13\Http\Requests\ProjectRestoreRequest;

class ProjectRestoreRequestTest extends TestCase
{
    public function test_project_restore_request_does_not_validate_body_id(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH', ['id' => 1]);

        $validator = $this->validatorForRequest($request, static fn (): array => $request->rules());

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->validated());
    }

    public function test_project_restore_request_does_not_validate_body_name(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH', ['name' => 'Test Project']);

        $validator = $this->validatorForRequest($request, static fn (): array => $request->rules());

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->validated());
    }

    public function test_rules_return_expected_structure(): void
    {
        $request = new ProjectRestoreRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('resolve_name_conflict', $rules);
        $this->assertCount(1, $rules);
    }

    public function test_resolve_name_conflict_is_sometimes_boolean(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH', ['resolve_name_conflict' => 'not-a-boolean']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'resolve_name_conflict');
    }

    public function test_resolve_name_conflict_is_optional(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH', []);

        $this->assertRequestValidationPasses($request, static fn (): array => $request->rules());
    }

    public function test_authorize_returns_false_when_user_cannot_restore(): void
    {
        $request = ProjectRestoreRequest::create('/', 'PATCH');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
