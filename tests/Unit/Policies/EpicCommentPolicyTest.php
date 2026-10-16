<?php

namespace Projects13\Tests\Unit\Policies;

use App\Models\User;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Projects13\Policies\EpicCommentPolicy;

class EpicCommentPolicyTest extends TestCase
{
    private EpicCommentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new EpicCommentPolicy;
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
        $comment = new EpicComment(['body' => 'Comment under test']);

        $this->assertTrue($this->policy->update($user, $comment));
    }

    public function test_delete_returns_true(): void
    {
        $user = new User;
        $comment = new EpicComment(['body' => 'Comment under test']);

        $this->assertTrue($this->policy->delete($user, $comment));
    }

    public function test_force_delete_returns_true(): void
    {
        $user = new User;
        $comment = new EpicComment(['body' => 'Comment under test']);

        $this->assertTrue($this->policy->forceDelete($user, $comment));
    }
}
