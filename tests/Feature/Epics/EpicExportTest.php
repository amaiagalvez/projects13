<?php

namespace Projects13\Tests\Feature\Epics;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The export downloads the same rows the list shows: the scope, the search and the advanced
 * filters reach it through the list query, so the file never drifts from the page.
 */
class EpicExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_the_export_holds_the_active_epics_matching_the_search(): void
    {
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create(['name' => 'Northern launch']);
        Epic::factory()->for($project)->create(['name' => 'Northern review']);
        Epic::factory()->for($project)->create(['name' => 'Southern launch']);
        Epic::factory()->for($project)->create(['name' => 'Northern archived', 'active' => false]);

        $this->assertEqualsCanonicalizing(
            ['Northern launch', 'Northern review'],
            $this->exportedNames(route('epics.export', ['search' => 'Northern'])),
        );
    }

    public function test_the_export_holds_the_epics_matching_an_advanced_filter(): void
    {
        Epic::factory()->for(Project::factory()->create(['name' => 'Harbour']))->create(['name' => 'Harbour epic']);
        Epic::factory()->for(Project::factory()->create(['name' => 'Mountain']))->create(['name' => 'Mountain epic']);

        $this->assertSame(
            ['Harbour epic'],
            $this->exportedNames(route('epics.export', [
                'filters' => ['project_name' => ['operator' => 'contains', 'value' => 'Harb']],
            ])),
        );
    }

    public function test_the_export_follows_the_archived_and_trash_scopes(): void
    {
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create(['name' => 'Active alpha']);
        Epic::factory()->for($project)->create(['name' => 'Archived alpha', 'active' => false]);
        Epic::factory()->for($project)->trashed()->create(['name' => 'Trashed alpha']);

        $this->assertSame(['Active alpha'], $this->exportedNames(route('epics.export')));
        $this->assertSame(['Archived alpha'], $this->exportedNames(route('epics.export', ['scope' => 'archived'])));
        $this->assertSame(['Trashed alpha'], $this->exportedNames(route('epics.export', ['scope' => 'trashed'])));
    }

    public function test_the_export_names_the_project_of_each_epic(): void
    {
        Epic::factory()->for(Project::factory()->create(['name' => 'Harbour']))->create(['name' => 'Harbour epic']);

        $rows = $this->exportedRows(route('epics.export'));

        $this->assertSame('Harbour', $rows[0][2]);
    }

    public function test_the_file_name_uses_the_current_language_and_marks_the_scope(): void
    {
        app()->setLocale('eu');
        $project = Project::factory()->create();
        Epic::factory()->for($project)->create(['name' => 'Active alpha']);
        Epic::factory()->for($project)->trashed()->create(['name' => 'Trashed alpha']);

        $active = $this->get(route('epics.export'));
        $active->assertOk();
        $this->assertStringContainsString('epikak_', (string) $active->headers->get('content-disposition'));

        $trashed = $this->get(route('epics.export', ['scope' => 'trashed']));
        $trashed->assertOk();
        $this->assertStringContainsString('epikak_zakarrontzia_', (string) $trashed->headers->get('content-disposition'));
    }

    public function test_every_list_offers_the_export_of_its_own_scope(): void
    {
        $this->get(route('epics.index'))
            ->assertOk()
            ->assertSee('action="'.route('epics.export').'"', false)
            ->assertSee('data-test="epic-excel-export"', false);

        $this->get(route('epics.archived.index'))
            ->assertOk()
            ->assertSee('action="'.route('epics.export').'"', false)
            ->assertSee('name="scope" value="archived"', false);

        $this->get(route('epics.trash.index'))
            ->assertOk()
            ->assertSee('action="'.route('epics.export').'"', false)
            ->assertSee('name="scope" value="trashed"', false);
    }

    /**
     * Download the file and return the epic names, skipping the headings row.
     *
     * @return list<string>
     */
    private function exportedNames(string $url): array
    {
        return array_map(fn (array $row): string => (string) $row[1], $this->exportedRows($url));
    }

    /**
     * Download the file and return its rows, skipping the headings row.
     *
     * @return list<array<int, mixed>>
     */
    private function exportedRows(string $url): array
    {
        $response = $this->get($url);

        $response->assertOk();
        $response->assertDownload();

        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        return array_values(array_slice(IOFactory::load($file->getFile()->getPathname())->getActiveSheet()->toArray(), 1));
    }
}
