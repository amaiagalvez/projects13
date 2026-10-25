<?php

namespace Projects13\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The permissions the project and epic policies check and the work group of the sidebar. `admin`
 * gets full access to both modules. Runs after the basics13 access seeder, which creates `admin`;
 * running it again duplicates nothing and takes no permission away from a role.
 */
class ProjectsAccessSeeder extends Seeder
{
    /**
     * Planning and timeline read through `projects.view`, like their requests. The work dashboard
     * and the calendar check nothing, so their links show to every account: each calendar is
     * narrowed by its own policies on the screen itself.
     *
     * @var list<array{key: string, label: string, sort: int, parent?: string, icon?: string, route?: string, active?: string, except?: string, permissions?: list<string>}>
     */
    private const MENUS = [
        ['key' => 'work', 'label' => 'Work', 'sort' => 100],
        ['key' => 'work-dashboard', 'parent' => 'work', 'label' => 'Work dashboard', 'sort' => 10, 'icon' => 'squares-2x2', 'route' => 'work.dashboard', 'active' => 'work.dashboard'],
        ['key' => 'projects', 'parent' => 'work', 'label' => 'Projects', 'sort' => 20, 'icon' => 'briefcase', 'route' => 'projects.index', 'active' => 'projects.*', 'permissions' => ['projects.view']],
        ['key' => 'epics', 'parent' => 'work', 'label' => 'Epics', 'sort' => 30, 'icon' => 'flag', 'route' => 'epics.index', 'active' => 'epics.*', 'permissions' => ['epics.view']],
        ['key' => 'planning', 'parent' => 'work', 'label' => 'Planning', 'sort' => 40, 'icon' => 'calendar-days', 'route' => 'planning', 'active' => 'planning', 'permissions' => ['projects.view']],
        ['key' => 'timeline', 'parent' => 'work', 'label' => 'Timeline', 'sort' => 50, 'icon' => 'bars-3-bottom-left', 'route' => 'timeline', 'active' => 'timeline', 'permissions' => ['projects.view']],
        ['key' => 'calendar', 'parent' => 'work', 'label' => 'Calendar', 'sort' => 60, 'icon' => 'calendar', 'route' => 'calendar', 'active' => 'calendar'],
    ];

    public function run(): void
    {
        seedPermissions(['projects', 'epics'], ['projects.manage', 'epics.manage']);
        seedMenus('projects13', self::MENUS);
    }
}
