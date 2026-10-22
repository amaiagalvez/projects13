<?php

namespace Projects13\Tests\Feature\Permissions;

use Basics13\Models\Role;
use Projects13\Tests\TestCase;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectPermissionSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_full_access_to_projects_and_epics(): void
    {
        $this->assertSame(8, Permission::query()->where('module', 'projects')->count());
        $this->assertSame(8, Permission::query()->where('module', 'epics')->count());
        $this->assertEqualsCanonicalizing(
            ['projects.manage', 'epics.manage'],
            Role::query()->where('slug', 'admin')->firstOrFail()->permissions()
                ->whereIn('module', ['projects', 'epics'])->pluck('slug')->all(),
        );
    }

    public function test_running_it_again_duplicates_nothing(): void
    {
        $counts = [DB::table('permissions')->count(), DB::table('permission_role')->count()];

        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_23_000000_seed_project_permissions.php';
        $migration->up();

        $this->assertSame($counts, [DB::table('permissions')->count(), DB::table('permission_role')->count()]);
    }
}
