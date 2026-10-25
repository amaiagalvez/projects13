<?php

namespace Projects13\Tests;

use Illuminate\Database\Seeder;
use Basics13\Database\Seeders\BasicsAccessSeeder;
use Projects13\Database\Seeders\ProjectsAccessSeeder;

/**
 * What `db:seed` gives the application for this package: basics13 first, then projects13.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([BasicsAccessSeeder::class, ProjectsAccessSeeder::class]);
    }
}
