<?php

namespace Projects13\Tests\Unit\Policies;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Tests\TestCase;
use Projects13\Policies\EpicPolicy;

class EpicPolicyTest extends TestCase
{
    private EpicPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new EpicPolicy;
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
        $epic = new Epic(['name' => 'Epic under test']);

        $this->assertTrue($this->policy->update($user, $epic));
    }

    public function test_comment_returns_true(): void
    {
        $user = new User;
        $epic = new Epic(['name' => 'Epic under test']);

        $this->assertTrue($this->policy->comment($user, $epic));
    }

    public function test_delete_returns_true(): void
    {
        $user = new User;
        $epic = new Epic(['name' => 'Epic under test']);

        $this->assertTrue($this->policy->delete($user, $epic));
    }

    public function test_restore_returns_true(): void
    {
        $user = new User;
        $epic = new Epic(['name' => 'Epic under test']);

        $this->assertTrue($this->policy->restore($user, $epic));
    }

    public function test_force_delete_returns_true(): void
    {
        $user = new User;
        $epic = new Epic(['name' => 'Epic under test']);

        $this->assertTrue($this->policy->forceDelete($user, $epic));
    }

    public function test_archive_returns_true(): void
    {
        $user = new User;
        $epic = new Epic(['name' => 'Epic under test']);

        $this->assertTrue($this->policy->archive($user, $epic));
    }

    public function test_activate_returns_true(): void
    {
        $user = new User;
        $epic = new Epic(['name' => 'Epic under test']);

        $this->assertTrue($this->policy->activate($user, $epic));
    }
}
