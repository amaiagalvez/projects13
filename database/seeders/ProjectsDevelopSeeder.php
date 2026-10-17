<?php

namespace Projects13\Database\Seeders;

use Illuminate\Database\Seeder;

class ProjectsDevelopSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DevelopProjectSeeder::class);
    }
}
