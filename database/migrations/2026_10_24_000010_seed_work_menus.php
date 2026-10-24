<?php

use Illuminate\Database\Migrations\Migration;

/**
 * The work group of the sidebar. Planning and timeline read through `projects.view`, like their
 * requests; the work dashboard checks nothing, so its link shows to every account.
 */
return new class extends Migration
{
    public function up(): void
    {
        seedMenus('projects13', [
            ['key' => 'work', 'label' => 'Work', 'sort' => 100],
            ['key' => 'work-dashboard', 'parent' => 'work', 'label' => 'Work dashboard', 'sort' => 10, 'icon' => 'squares-2x2', 'route' => 'work.dashboard', 'active' => 'work.dashboard'],
            ['key' => 'projects', 'parent' => 'work', 'label' => 'Projects', 'sort' => 20, 'icon' => 'briefcase', 'route' => 'projects.index', 'active' => 'projects.*', 'permissions' => ['projects.view']],
            ['key' => 'epics', 'parent' => 'work', 'label' => 'Epics', 'sort' => 30, 'icon' => 'flag', 'route' => 'epics.index', 'active' => 'epics.*', 'permissions' => ['epics.view']],
            ['key' => 'planning', 'parent' => 'work', 'label' => 'Planning', 'sort' => 40, 'icon' => 'calendar-days', 'route' => 'planning', 'active' => 'planning', 'permissions' => ['projects.view']],
            ['key' => 'timeline', 'parent' => 'work', 'label' => 'Timeline', 'sort' => 50, 'icon' => 'bars-3-bottom-left', 'route' => 'timeline', 'active' => 'timeline', 'permissions' => ['projects.view']],
        ]);
    }

    public function down(): void
    {
        removeMenus('projects13');
    }
};
