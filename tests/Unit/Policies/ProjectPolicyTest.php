<?php

namespace Projects13\Tests\Unit\Policies;

use App\Models\User;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Policies\ProjectPolicy;

class ProjectPolicyTest extends TestCase
{
    private ProjectPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ProjectPolicy;
    }

    public function test_view_any_returns_true(): void
    {
        $user = new User;

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_create_returns_true(): void
    {
        $user = new User;

        $this->assertTrue($this->policy->create($user));
    }

    public function test_update_returns_true(): void
    {
        $user = new User;
        $project = new Project(['name' => 'Project under test']);

        $this->assertTrue($this->policy->update($user, $project));
    }

    public function test_delete_returns_true(): void
    {
        $user = new User;
        $project = new Project(['name' => 'Project under test']);

        $this->assertTrue($this->policy->delete($user, $project));
    }

    public function test_restore_returns_true(): void
    {
        $user = new User;
        $project = new Project(['name' => 'Project under test']);

        $this->assertTrue($this->policy->restore($user, $project));
    }

    public function test_force_delete_returns_true(): void
    {
        $user = new User;
        $project = new Project(['name' => 'Project under test']);

        $this->assertTrue($this->policy->forceDelete($user, $project));
    }

    public function test_archive_returns_true(): void
    {
        $user = new User;
        $project = new Project(['name' => 'Project under test']);

        $this->assertTrue($this->policy->archive($user, $project));
    }

    public function test_activate_returns_true(): void
    {
        $user = new User;
        $project = new Project(['name' => 'Project under test']);

        $this->assertTrue($this->policy->activate($user, $project));
    }
}
