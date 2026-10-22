<?php

namespace Projects13\Tests\Feature\Migrations;

use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_epic_migration_can_be_rolled_back(): void
    {
        $connectionName = 'projects13_epic_migration_test';
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
            Schema::create('PRO_projects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->softDeletes();
            });

            $migration = require dirname(__DIR__, 3).'/database/migrations/2026_09_30_175706_create_epics_table.php';
            $migration->up();

            $this->assertTrue(Schema::hasTable('PRO_epics'));

            $columns = Schema::getColumnListing('PRO_epics');
            $this->assertContains('id', $columns);
            $this->assertContains('name', $columns);
            $this->assertContains('start_date', $columns);
            $this->assertContains('end_date', $columns);
            $this->assertContains('project_id', $columns);
            $this->assertContains('notes', $columns);
            $this->assertContains('active', $columns);
            $this->assertContains('created_at', $columns);
            $this->assertContains('updated_at', $columns);
            $this->assertContains('deleted_at', $columns);
            $this->assertContains('created_by', $columns);
            $this->assertContains('updated_by', $columns);
            $this->assertContains('deleted_by', $columns);

            $migration->down();

            $this->assertFalse(Schema::hasTable('PRO_epics'));
        } finally {
            DB::purge($connectionName);
            config([
                'database.default' => $originalDefault,
                "database.connections.{$connectionName}" => null,
            ]);
        }
    }

    public function test_epic_migration_creates_unique_active_name_index_scoped_to_project(): void
    {
        $connectionName = 'projects13_epic_migration_test_index';
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
            Schema::create('PRO_projects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->softDeletes();
            });

            $migration = require dirname(__DIR__, 3).'/database/migrations/2026_09_30_175706_create_epics_table.php';
            $migration->up();

            $indexes = DB::select("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='PRO_epics'");
            $uniqueIndex = collect($indexes)->firstWhere('name', 'PRO_epics_project_id_active_name_unique');

            $this->assertNotNull($uniqueIndex);
            $this->assertStringContainsString('WHERE "deleted_at" IS NULL', $uniqueIndex->sql);
            $this->assertStringContainsString('"project_id"', $uniqueIndex->sql);
        } finally {
            DB::purge($connectionName);
            config([
                'database.default' => $originalDefault,
                "database.connections.{$connectionName}" => null,
            ]);
        }
    }
}
