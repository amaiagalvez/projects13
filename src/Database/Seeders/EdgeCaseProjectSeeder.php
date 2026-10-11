<?php

namespace Projects13\Database\Seeders;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Database\Seeder;
use Customers13\Models\Customer;

class EdgeCaseProjectSeeder extends Seeder
{
    public function run(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $customer = Customer::query()
                ->where('name', "Customer for project without epics {$index}")
                ->firstOrFail();

            Project::factory()->create([
                'name' => "Project without epics {$index}",
                'customer_id' => $customer->id,
            ]);
        }

        for ($index = 0; $index < 3; $index++) {
            $customer = Customer::query()
                ->where('name', "Customer for epic without comments {$index}")
                ->firstOrFail();
            $project = Project::factory()->create([
                'name' => "Project for epic without comments {$index}",
                'customer_id' => $customer->id,
            ]);

            Epic::factory()->create([
                'name' => "Epic without comments {$index}",
                'project_id' => $project->id,
            ]);
        }
    }
}
