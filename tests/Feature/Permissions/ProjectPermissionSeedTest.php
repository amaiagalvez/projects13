<?php

namespace Projects13\Tests\Feature\Permissions;

use Basics13\Models\Role;
use Projects13\Tests\TestCase;
use Basics13\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Database\Seeders\ProjectsAccessSeeder;

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
        $counts = [DB::table('BAS_permissions')->count(), DB::table('BAS_permission_role')->count()];

        $this->seed(ProjectsAccessSeeder::class);

        $this->assertSame($counts, [DB::table('BAS_permissions')->count(), DB::table('BAS_permission_role')->count()]);
    }
}
