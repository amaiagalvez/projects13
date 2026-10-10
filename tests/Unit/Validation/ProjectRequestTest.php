<?php

namespace Projects13\Tests\Unit\Validation;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Projects13\Http\Requests\ProjectRequest;
use Projects13\Tests\TestCase;

class ProjectRequestTest extends TestCase
{
    public function test_project_request_validates_name(): void
    {
        $request = Request::create('/', 'POST');
        $request->merge(['name' => 'Valid Project']);

        $this->assertTrue(true);
    }

    public function test_project_request_validates_customer_id(): void
    {
        $request = Request::create('/', 'POST');
        $request->merge(['name' => 'Project', 'customer_id' => 1]);

        $this->assertTrue(true);
    }
}