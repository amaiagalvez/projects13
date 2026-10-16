<?php

namespace Projects13\Tests\Feature\Migrations;

use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectMigrationTest extends TestCase
{
    public function test_project_migration_can_be_rolled_back(): void
    {
        $connectionName = 'projects13_migration_test';
        $originalDefault = config('database.default');
        $sqliteConnection = config('database.connections.sqlite');

        if (! is_array($sqliteConnection)) {
            throw new \RuntimeException('The SQLite test connection is not configured.');
        }

        config([
            "database.connections.{$connectionName}" => array_merge($sqliteConnection, [
                'database' => ':memory:',
            ]),
            'database.default' => $connectionName,
        ]);

        DB::purge($connectionName);

        try {

            $migration = require dirname(__DIR__, 3).'/database/migrations/2026_09_28_173607_create_projects_table.php';
            $migration->up();

            $this->assertTrue(Schema::hasTable('PRO_projects'));

            $columns = Schema::getColumnListing('PRO_projects');
            $this->assertContains('id', $columns);
            $this->assertContains('name', $columns);
            $this->assertContains('start_date', $columns);
            $this->assertContains('end_date', $columns);
            $this->assertContains('notes', $columns);
            $this->assertContains('active', $columns);
            $this->assertContains('created_at', $columns);
            $this->assertContains('updated_at', $columns);
            $this->assertContains('deleted_at', $columns);
            $this->assertContains('created_by', $columns);
            $this->assertContains('updated_by', $columns);
            $this->assertContains('deleted_by', $columns);

            $migration->down();

            $this->assertFalse(Schema::hasTable('PRO_projects'));
        } finally {
            DB::purge($connectionName);
            config([
                'database.default' => $originalDefault,
                "database.connections.{$connectionName}" => null,
            ]);
        }
    }

    public function test_project_migration_creates_unique_active_name_index(): void
    {
        $connectionName = 'projects13_migration_test_index';
        $originalDefault = config('database.default');
        $sqliteConnection = config('database.connections.sqlite');

        if (! is_array($sqliteConnection)) {
            throw new \RuntimeException('The SQLite test connection is not configured.');
        }

        config([
            "database.connections.{$connectionName}" => array_merge($sqliteConnection, [
                'database' => ':memory:',
            ]),
            'database.default' => $connectionName,
        ]);

        DB::purge($connectionName);

        try {

            $migration = require dirname(__DIR__, 3).'/database/migrations/2026_09_28_173607_create_projects_table.php';
            $migration->up();

            $indexes = DB::select("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='PRO_projects'");
            $uniqueIndex = collect($indexes)->firstWhere('name', 'PRO_projects_active_name_unique');

            $this->assertNotNull($uniqueIndex);
            $this->assertStringContainsString('WHERE "deleted_at" IS NULL', $uniqueIndex->sql);
        } finally {
            DB::purge($connectionName);
            config([
                'database.default' => $originalDefault,
                "database.connections.{$connectionName}" => null,
            ]);
        }
    }
}
