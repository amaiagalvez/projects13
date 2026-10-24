<?php

use Illuminate\Support\Facades\DB;
use Basics13\Support\NavigationMenu;
use Illuminate\Database\Migrations\Migration;

/**
 * The calendar in the work group. It checks no permission: every account keeps a personal calendar,
 * and the project and epic calendars are narrowed by their own policies on the screen itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        seedMenus('projects13', [
            ['key' => 'calendar', 'parent' => 'work', 'label' => 'Calendar', 'sort' => 60, 'icon' => 'calendar', 'route' => 'calendar', 'active' => 'calendar'],
        ]);
    }

    public function down(): void
    {
        // removeMenus() drops every item of the package; only this one is taken back here.
        DB::table('BAS_menus')->where('key', 'calendar')->delete();

        NavigationMenu::flush();
    }
};
