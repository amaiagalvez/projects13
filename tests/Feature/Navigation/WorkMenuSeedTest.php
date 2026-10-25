<?php

namespace Projects13\Tests\Feature\Navigation;

use Basics13\Models\Menu;
use Basics13\Models\User;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Basics13\Support\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Database\Seeders\ProjectsAccessSeeder;

class WorkMenuSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_it_again_duplicates_nothing(): void
    {
        $counts = [DB::table('BAS_menus')->count(), DB::table('BAS_menu_permission')->count()];

        $this->seed(ProjectsAccessSeeder::class);

        $this->assertSame($counts, [DB::table('BAS_menus')->count(), DB::table('BAS_menu_permission')->count()]);
        $this->assertSame(['projects.view'], Menu::query()->where('key', 'planning')->firstOrFail()->permissions()->pluck('slug')->all());
        $this->assertSame([], Menu::query()->where('key', 'calendar')->firstOrFail()->permissions()->pluck('slug')->all());
    }

    public function test_projects_hang_from_the_work_group_for_whoever_views_projects(): void
    {
        $work = collect(NavigationMenu::for(User::factory()->withPermissions(['projects.view'])->create()))
            ->firstWhere('item.key', 'work');

        $this->assertNotNull($work);
        $this->assertSame(
            ['work-dashboard', 'projects', 'planning', 'timeline', 'calendar'],
            array_column($work['children'], 'key'),
        );
    }

    public function test_without_permissions_only_the_work_dashboard_and_the_calendar_show(): void
    {
        $work = collect(NavigationMenu::for(User::factory()->create()))->firstWhere('item.key', 'work');

        $this->assertSame(['work-dashboard', 'calendar'], array_column($work['children'] ?? [], 'key'));
    }
}
