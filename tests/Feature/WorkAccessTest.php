<?php

namespace Projects13\Tests\Feature;

use Basics13\Models\User;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_work_dashboard_and_the_calendar_need_projects_view(): void
    {
        $this->actingAs(User::factory()->withPermissions(['users.view_own'])->create());

        $this->get(route('work.dashboard'))->assertForbidden();
        $this->get(route('calendar'))->assertForbidden();
    }

    public function test_an_account_without_a_role_cannot_open_the_work_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('work.dashboard'))->assertForbidden();
        $this->get(route('calendar'))->assertForbidden();
    }

    public function test_a_projects_viewer_opens_the_work_dashboard_and_the_calendar(): void
    {
        $this->actingAs(User::factory()->withPermissions(['projects.view'])->create());

        $this->get(route('work.dashboard'))->assertOk();
        $this->get(route('calendar'))->assertOk();
    }
}
