<?php

namespace Projects13\Database\Seeders;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Database\Seeder;

class EdgeCaseProjectSeeder extends Seeder
{
    public function run(): void
    {
        for ($index = 0; $index < 3; $index++) {
            Project::factory()->create([
                'name' => "Project without epics {$index}",
            ]);
        }

        for ($index = 0; $index < 3; $index++) {
            $project = Project::factory()->create([
                'name' => "Project for epic without comments {$index}",
            ]);

            Epic::factory()->create([
                'name' => "Epic without comments {$index}",
                'project_id' => $project->id,
            ]);
        }
    }
}
