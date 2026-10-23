<?php

namespace Projects13\Tests\Unit\Validation;

use Projects13\Tests\TestCase;
use Projects13\Http\Requests\ProjectMemberListRequest;

class ProjectMemberListRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new ProjectMemberListRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_empty_array(): void
    {
        $request = new ProjectMemberListRequest;
        $this->assertSame([], $request->rules());
    }
}
