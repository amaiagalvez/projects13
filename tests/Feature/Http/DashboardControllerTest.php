<?php

namespace Projects13\Tests\Feature\Http;

use App\Models\User;
use Projects13\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('work.dashboard'))->assertRedirect(route('login'));
    }

    public function test_the_dashboard_renders_the_summary(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('work.dashboard'))
            ->assertOk()
            ->assertViewHas('summary')
            ->assertSee(__('Work dashboard'));
    }
}
