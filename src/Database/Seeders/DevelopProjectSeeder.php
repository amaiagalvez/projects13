<?php

namespace Projects13\Database\Seeders;

use App\Models\User;
use RuntimeException;
use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Database\Seeder;
use Projects13\Models\EpicComment;
use Projects13\Database\Factories\EpicFactory;
use Projects13\Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DevelopProjectSeeder extends Seeder
{
    private const STATE_WEIGHTS = ['active' => 12, 'archived' => 5, 'trashed' => 3];

    private const EPICS_PER_PROJECT = 4;

    private const COMMENTS_PER_EPIC = 3;

    /**
     * @var array<string, int>
     */
    private array $cursors = ['projects' => 0, 'epics' => 0];

    public function run(int $projects = 300): void
    {
        if ($projects <= 0) {
            return;
        }

        $authors = User::query()->orderBy('id')->limit(5)->get();

        if ($authors->isEmpty()) {
            throw new RuntimeException('Seed users before running DevelopProjectSeeder.');
        }

        $states = $this->states();
        $epicNumber = 0;
        $commentNumber = 0;

        for ($projectNumber = 1; $projectNumber <= $projects; $projectNumber++) {
            /** @var ProjectFactory $projectFactory */
            $projectFactory = Project::factory();
            $projectFactory = $this->withState(
                'projects',
                $projectFactory,
                $states,
            );
            $project = $projectFactory->createOne([
                'name' => sprintf('Perf Project %05d', $projectNumber),
            ]);

            for ($epicIndex = 1; $epicIndex <= self::EPICS_PER_PROJECT; $epicIndex++) {
                $epicNumber++;
                $epic = $this->createEpicWithinProject(
                    $project,
                    $epicNumber,
                    $states,
                );

                for ($commentIndex = 1; $commentIndex <= self::COMMENTS_PER_EPIC; $commentIndex++) {
                    $commentNumber++;
                    EpicComment::factory()->createOne([
                        'epic_id' => $epic->id,
                        'user_id' => $authors->random()->id,
                        'body' => sprintf('Perf comment %05d.', $commentNumber),
                    ]);
                }
            }
        }
    }

    /**
     * Create an epic with dates constrained to the project's date range.
     *
     * @param  list<string>  $states
     */
    private function createEpicWithinProject(Project $project, int $epicNumber, array $states): Epic
    {
        $projectStart = CarbonImmutable::parse($project->start_date);
        $projectEnd = $project->end_date
            ? CarbonImmutable::parse($project->end_date)
            : CarbonImmutable::now()->addYear();

        /** @var EpicFactory $factory */
        $factory = Epic::factory();
        $factory = $this->withState('epics', $factory, $states);

        if (fake()->boolean(20)) {
            return $factory->createOne([
                'name' => sprintf('Perf Epic %05d', $epicNumber),
                'project_id' => $project->id,
                'start_date' => null,
                'end_date' => null,
            ]);
        }

        $latestStart = $projectEnd->subDay();

        if ($latestStart->lessThanOrEqualTo($projectStart)) {
            $startDate = $projectStart->format('Y-m-d');
            $endDate = $projectEnd->format('Y-m-d');
        } else {
            $startDate = fake()->dateTimeBetween($projectStart, $latestStart)->format('Y-m-d');
            $endDate = fake()->dateTimeBetween($startDate.' +1 day', $projectEnd)->format('Y-m-d');
        }

        return $factory->createOne([
            'name' => sprintf('Perf Epic %05d', $epicNumber),
            'project_id' => $project->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * @return list<string>
     */
    private function states(): array
    {
        $states = [];

        foreach (self::STATE_WEIGHTS as $state => $weight) {
            array_push($states, ...array_fill(0, $weight, $state));
        }

        return $states;
    }

    /**
     * Move a factory to the next state in the active/archive/trash rotation.
     *
     * @template TFactory of ProjectFactory|EpicFactory
     *
     * @param  TFactory  $factory
     * @param  list<string>  $states
     * @return TFactory
     */
    private function withState(string $level, Factory $factory, array $states): Factory
    {
        $state = $states[$this->cursors[$level]++ % count($states)];

        return match ($state) {
            'archived' => $factory->archived(),
            'trashed' => $factory->trashed(),
            default => $factory,
        };
    }
}
