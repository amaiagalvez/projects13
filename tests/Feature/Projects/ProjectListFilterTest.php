<?php

namespace Projects13\Tests\Feature\Projects;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Basics13\Queries\ListQueryBase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The advanced filters through the HTTP lists: every state applies them, they combine with the
 * search, and the pagination keeps them.
 */
class ProjectListFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_a_date_range_filter_keeps_the_projects_starting_inside_it(): void
    {
        Project::factory()->create(['name' => 'Spring project', 'start_date' => '2026-04-10']);
        Project::factory()->create(['name' => 'Autumn project', 'start_date' => '2026-10-10']);

        $this->get(route('projects.index', $this->filters(['start_date' => ['operator' => 'between', 'value' => ['2026-04-01', '2026-04-30']]])))
            ->assertOk()
            ->assertSee('Spring project')
            ->assertDontSee('Autumn project');
    }

    public function test_the_archived_and_trash_lists_apply_the_filters_too(): void
    {
        Project::factory()->create(['name' => 'Archived alpha', 'active' => false]);
        Project::factory()->create(['name' => 'Archived beta', 'active' => false]);
        Project::factory()->trashed()->create(['name' => 'Trashed alpha']);
        Project::factory()->trashed()->create(['name' => 'Trashed beta']);
        $alpha = $this->filters(['name' => ['operator' => 'contains', 'value' => 'alpha']]);

        $this->get(route('projects.archived.index', $alpha))
            ->assertOk()
            ->assertSee('Archived alpha')
            ->assertDontSee('Archived beta');

        $this->get(route('projects.trash.index', $alpha))
            ->assertOk()
            ->assertSee('Trashed alpha')
            ->assertDontSee('Trashed beta');
    }

    public function test_the_search_and_the_filters_narrow_the_list_together(): void
    {
        Project::factory()->create(['name' => 'Northern launch', 'start_date' => '2026-04-10']);
        Project::factory()->create(['name' => 'Northern review', 'start_date' => '2026-10-10']);
        Project::factory()->create(['name' => 'Southern launch', 'start_date' => '2026-04-12']);

        $this->get(route('projects.index', [
            'search' => 'Northern',
            ...$this->filters(['start_date' => ['operator' => 'before', 'value' => '2026-06-01']]),
        ]))
            ->assertOk()
            ->assertViewHas('projects', fn ($projects): bool => $projects->pluck('name')->all() === ['Northern launch']);
    }

    public function test_the_pagination_links_keep_the_filters(): void
    {
        Project::factory()->count(ListQueryBase::PER_PAGE + 1)->sequence(
            fn ($sequence): array => ['name' => 'Filtered project '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)],
        )->create();
        $filters = ['name' => ['operator' => 'starts_with', 'value' => 'Filtered']];

        $this->get(route('projects.index', $this->filters($filters)))
            ->assertOk()
            ->assertViewHas('projects', function ($projects) use ($filters): bool {
                parse_str((string) parse_url((string) $projects->nextPageUrl(), PHP_URL_QUERY), $query);

                return $projects->total() === ListQueryBase::PER_PAGE + 1 && ($query['filters'] ?? null) === $filters;
            });
    }

    /**
     * @param  array<string, array{operator: string, value?: mixed}>  $filters
     * @return array{filters: array<string, array{operator: string, value?: mixed}>}
     */
    private function filters(array $filters): array
    {
        return ['filters' => $filters];
    }
}
