<?php

namespace Projects13\Tests\Feature\Projects;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The export downloads the same rows the list shows: the scope, the search and the advanced
 * filters reach it through the list query, so the file never drifts from the page.
 */
class ProjectExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn();
    }

    public function test_the_export_holds_the_active_projects_matching_the_search(): void
    {
        Project::factory()->create(['name' => 'Northern launch']);
        Project::factory()->create(['name' => 'Northern review']);
        Project::factory()->create(['name' => 'Southern launch']);
        Project::factory()->create(['name' => 'Northern archived', 'active' => false]);

        $this->assertEqualsCanonicalizing(
            ['Northern launch', 'Northern review'],
            $this->exportedNames(route('projects.export', ['search' => 'Northern'])),
        );
    }

    public function test_the_export_holds_the_projects_matching_an_advanced_filter(): void
    {
        Project::factory()->create(['name' => 'Spring project', 'start_date' => '2026-04-10']);
        Project::factory()->create(['name' => 'Autumn project', 'start_date' => '2026-10-10']);

        $this->assertSame(
            ['Spring project'],
            $this->exportedNames(route('projects.export', [
                'filters' => ['start_date' => ['operator' => 'between', 'value' => ['2026-04-01', '2026-04-30']]],
            ])),
        );
    }

    public function test_the_export_follows_the_archived_and_trash_scopes(): void
    {
        Project::factory()->create(['name' => 'Active alpha']);
        Project::factory()->create(['name' => 'Archived alpha', 'active' => false]);
        Project::factory()->trashed()->create(['name' => 'Trashed alpha']);

        $this->assertSame(['Active alpha'], $this->exportedNames(route('projects.export')));
        $this->assertSame(['Archived alpha'], $this->exportedNames(route('projects.export', ['scope' => 'archived'])));
        $this->assertSame(['Trashed alpha'], $this->exportedNames(route('projects.export', ['scope' => 'trashed'])));
    }

    public function test_the_file_name_uses_the_current_language(): void
    {
        app()->setLocale('eu');
        Project::factory()->create(['name' => 'Alpha']);

        $response = $this->get(route('projects.export'));

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringContainsString('proiektuak_', (string) $response->headers->get('content-disposition'));
    }

    public function test_the_file_name_marks_the_archived_and_trash_scopes(): void
    {
        app()->setLocale('eu');
        Project::factory()->create(['name' => 'Archived alpha', 'active' => false]);
        Project::factory()->trashed()->create(['name' => 'Trashed alpha']);

        $archived = $this->get(route('projects.export', ['scope' => 'archived']));
        $archived->assertOk();
        $this->assertStringContainsString('proiektuak_artxibatuak_', (string) $archived->headers->get('content-disposition'));

        $trashed = $this->get(route('projects.export', ['scope' => 'trashed']));
        $trashed->assertOk();
        $this->assertStringContainsString('proiektuak_zakarrontzia_', (string) $trashed->headers->get('content-disposition'));
    }

    public function test_the_archived_and_trash_lists_export_their_own_scope(): void
    {
        Project::factory()->create(['name' => 'Archived alpha', 'active' => false]);
        Project::factory()->trashed()->create(['name' => 'Trashed alpha']);

        $this->get(route('projects.archived.index'))
            ->assertOk()
            ->assertSee('action="'.route('projects.export').'"', false)
            ->assertSee('name="scope" value="archived"', false);

        $this->get(route('projects.trash.index'))
            ->assertOk()
            ->assertSee('action="'.route('projects.export').'"', false)
            ->assertSee('name="scope" value="trashed"', false);
    }

    /**
     * Download the file and return the project names, skipping the headings row.
     *
     * @return list<string>
     */
    private function exportedNames(string $url): array
    {
        $response = $this->get($url);

        $response->assertOk();
        $response->assertDownload();

        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        $sheet = IOFactory::load($file->getFile()->getPathname())->getActiveSheet();
        $names = [];

        foreach (array_slice($sheet->toArray(), 1) as $row) {
            $names[] = (string) $row[1];
        }

        return $names;
    }
}
