<?php

namespace Projects13\Tests\Unit\Validation;

use Projects13\Tests\TestCase;
use Projects13\Http\Requests\ProjectMemberRequest;
use Projects13\Http\Requests\ProjectMemberDestroyRequest;
use Projects13\Http\Requests\ProjectMemberCandidatesRequest;

/**
 * The member routes sit behind `auth`, but each request still refuses a request without a user
 * instead of asking the policy about nobody.
 */
class ProjectMemberGuestRequestsTest extends TestCase
{
    public function test_a_guest_is_never_authorized(): void
    {
        $this->assertFalse((new ProjectMemberRequest)->authorize());
        $this->assertFalse((new ProjectMemberDestroyRequest)->authorize());
        $this->assertFalse((new ProjectMemberCandidatesRequest)->authorize());
    }

    public function test_removing_a_member_takes_no_input(): void
    {
        $this->assertSame([], (new ProjectMemberDestroyRequest)->rules());
    }
}
