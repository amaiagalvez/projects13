<?php

namespace Projects13\Tests\Unit\Validation;

use Illuminate\Routing\Route;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Projects13\Http\Requests\ProjectRequest;
use Projects13\Http\Requests\UniqueProjectName;

class UniqueProjectNameTest extends TestCase
{
    public function test_passes_when_name_is_not_taken(): void
    {
        $request = ProjectRequest::create('/', 'POST');
        $rule = new UniqueProjectName($request);

        $this->assertTrue($rule->passes('name', 'New Project'));
    }

    public function test_fails_when_active_project_with_same_name_exists(): void
    {
        Project::factory()->create(['name' => 'Existing Project']);
        $request = ProjectRequest::create('/', 'POST');
        $rule = new UniqueProjectName($request);

        $this->assertFalse($rule->passes('name', 'Existing Project'));
    }

    public function test_passes_when_updating_the_same_project(): void
    {
        $project = Project::factory()->create(['name' => 'Existing Project']);
        $request = new ProjectRequest;
        $request->setMethod('PUT');
        $request->replace(['name' => 'Existing Project']);
        $route = new Route('PUT', '/', fn () => null);
        $route->bind($request);
        $route->setParameter('project', $project);
        $request->setRouteResolver(fn () => $route);

        $rule = new UniqueProjectName($request);
        $this->assertTrue($rule->passes('name', 'Existing Project'));
    }

    public function test_fails_when_soft_deleted_project_with_same_name_exists(): void
    {
        $project = Project::factory()->create(['name' => 'Deleted Project']);
        $project->delete();
        $request = ProjectRequest::create('/', 'POST');
        $rule = new UniqueProjectName($request);

        $this->assertFalse($rule->passes('name', 'Deleted Project'));
    }

    public function test_passes_when_reusing_soft_deleted_name_with_flag(): void
    {
        $project = Project::factory()->create(['name' => 'Deleted Project']);
        $project->delete();
        $request = ProjectRequest::create('/', 'POST', ['reuse_deleted_name' => true]);
        $rule = new UniqueProjectName($request);

        $this->assertTrue($rule->passes('name', 'Deleted Project'));
    }

    public function test_message_returns_the_expected_string(): void
    {
        $request = ProjectRequest::create('/', 'POST');
        $rule = new UniqueProjectName($request);

        $this->assertSame('The name has already been taken.', $rule->message());
    }

    public function test_integration_works_through_validator(): void
    {
        $existing = Project::factory()->create(['name' => 'Taken Name']);
        $request = ProjectRequest::create('/', 'POST', ['name' => 'Taken Name']);
        $validator = Validator::make($request->all(), [
            'name' => ['required', new UniqueProjectName($request)],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('The name has already been taken.', $validator->errors()->first('name'));

        $request = ProjectRequest::create('/', 'POST', ['name' => 'Unique Name']);
        $validator = Validator::make($request->all(), [
            'name' => ['required', new UniqueProjectName($request)],
        ]);

        $this->assertFalse($validator->fails());
    }
}
