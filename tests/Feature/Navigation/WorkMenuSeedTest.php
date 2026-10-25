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
        $this->assertSame(['projects.view'], Menu::query()->where('key', 'calendar')->firstOrFail()->permissions()->pluck('slug')->all());
    }

    public function test_projects_hang_from_the_projects_group_for_whoever_views_projects(): void
    {
        $group = collect(NavigationMenu::for(User::factory()->withPermissions(['projects.view'])->create()))
            ->firstWhere('item.key', 'projects13');

        $this->assertNotNull($group);
        $this->assertSame(
            ['work-dashboard', 'projects', 'planning', 'timeline', 'calendar'],
            array_column(array_column($group['children'], 'item'), 'key'),
        );
    }

    public function test_without_projects_access_the_group_does_not_show(): void
    {
        $this->assertNull(collect(NavigationMenu::for(User::factory()->create()))->firstWhere('item.key', 'projects13'));
    }

    public function test_the_old_work_group_is_gone(): void
    {
        DB::table('BAS_menus')->insert(['key' => 'work', 'package' => 'projects13', 'label' => 'Work', 'sort' => 100, 'created_at' => now(), 'updated_at' => now()]);

        $this->seed(ProjectsAccessSeeder::class);

        $this->assertFalse(DB::table('BAS_menus')->where('key', 'work')->exists());
    }
}
