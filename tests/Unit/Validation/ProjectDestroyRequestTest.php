<?php

namespace Projects13\Tests\Unit\Validation;

use Projects13\Tests\TestCase;
use Projects13\Http\Requests\ProjectDestroyRequest;

class ProjectDestroyRequestTest extends TestCase
{
    public function test_project_destroy_request_does_not_validate_body_fields(): void
    {
        $request = ProjectDestroyRequest::create('/', 'DELETE', ['id' => 1]);

        $validator = $this->validatorForRequest($request, static fn (): array => $request->rules());

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->validated());
    }
}
