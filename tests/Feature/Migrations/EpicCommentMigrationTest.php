<?php

namespace Projects13\Tests\Feature\Migrations;

use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class EpicCommentMigrationTest extends TestCase
{
    public function test_epic_comment_migration_can_be_rolled_back(): void
    {
        $connectionName = 'projects13_epic_comment_migration_test';
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
            // Create users, customers, projects, and epics tables first (foreign key dependencies)
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
            });

            Schema::create('CUM_customers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->softDeletes();
            });

            Schema::create('PRO_projects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->foreignId('customer_id')->constrained('CUM_customers');
                $table->softDeletes();
            });

            Schema::create('PRO_epics', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->foreignId('project_id')->constrained('PRO_projects');
                $table->softDeletes();
            });

            $migration = require dirname(__DIR__, 3).'/src/Database/Migrations/2026_09_30_175707_create_epic_comments_table.php';
            $migration->up();

            $this->assertTrue(Schema::hasTable('PRO_epic_comments'));

            $columns = Schema::getColumnListing('PRO_epic_comments');
            $this->assertContains('id', $columns);
            $this->assertContains('body', $columns);
            $this->assertContains('user_id', $columns);
            $this->assertContains('epic_id', $columns);
            $this->assertContains('notes', $columns);
            $this->assertContains('active', $columns);
            $this->assertContains('created_at', $columns);
            $this->assertContains('updated_at', $columns);
            $this->assertContains('deleted_at', $columns);
            $this->assertContains('created_by', $columns);
            $this->assertContains('updated_by', $columns);
            $this->assertContains('deleted_by', $columns);

            $migration->down();

            $this->assertFalse(Schema::hasTable('PRO_epic_comments'));
        } finally {
            DB::purge($connectionName);
            config([
                'database.default' => $originalDefault,
                "database.connections.{$connectionName}" => null,
            ]);
        }
    }

    public function test_epic_comment_migration_creates_foreign_keys(): void
    {
        $connectionName = 'projects13_epic_comment_fk_test';
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
            // Create users, customers, projects, and epics tables first (foreign key dependencies)
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
            });

            Schema::create('CUM_customers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->softDeletes();
            });

            Schema::create('PRO_projects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->foreignId('customer_id')->constrained('CUM_customers');
                $table->softDeletes();
            });

            Schema::create('PRO_epics', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->foreignId('project_id')->constrained('PRO_projects');
                $table->softDeletes();
            });

            $migration = require dirname(__DIR__, 3).'/src/Database/Migrations/2026_09_30_175707_create_epic_comments_table.php';
            $migration->up();

            $foreignKeys = DB::select("SELECT * FROM pragma_foreign_key_list('PRO_epic_comments')");

            $userIdFk = collect($foreignKeys)->firstWhere('from', 'user_id');
            $this->assertNotNull($userIdFk);
            $this->assertSame('users', $userIdFk->table);
            $this->assertSame('id', $userIdFk->to);

            $epicIdFk = collect($foreignKeys)->firstWhere('from', 'epic_id');
            $this->assertNotNull($epicIdFk);
            $this->assertSame('PRO_epics', $epicIdFk->table);
            $this->assertSame('id', $epicIdFk->to);
        } finally {
            DB::purge($connectionName);
            config([
                'database.default' => $originalDefault,
                "database.connections.{$connectionName}" => null,
            ]);
        }
    }
}
