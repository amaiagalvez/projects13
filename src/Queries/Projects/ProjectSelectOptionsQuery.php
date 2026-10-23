<?php

namespace Projects13\Queries\Projects;

use Projects13\Models\Project;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Projects13\Queries\ProjectVisibility;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Contracts\ProjectDisplayIntegration;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectSelectOptionsQuery extends ListQueryBase
{
    public function __construct(
        private readonly ProjectQueryIntegration $queryIntegration,
        private readonly ProjectDisplayIntegration $displayIntegration,
    ) {}

    /**
     * @return Collection<int, array{id: int, text: string}>
     */
    public function search(string $search): Collection
    {
        $query = Project::query()
            ->where('PRO_projects.active', true)
            // Selected before the integration is asked: it adds whatever columns it needs on top of
            // these, and a select applied afterwards would throw them away.
            ->select(['PRO_projects.id', 'PRO_projects.name']);

        $this->queryIntegration->scope($query, 'project', 'project_related');
        // The selector is where an epic gets its project, so it offers the projects the user edits.
        (new ProjectVisibility)->restrictToEditable($query, 'PRO_projects.id');

        /** @var non-empty-list<literal-string> $columns An integration appends to the base column; it never replaces it. */
        $columns = $this->queryIntegration->searchColumns('project', ['PRO_projects.name']);

        $this->whereMatches($query, $search, $columns);

        return $query
            ->orderBy('PRO_projects.name')
            ->orderBy('PRO_projects.id')
            ->limit(self::PER_PAGE)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'text' => $this->displayIntegration->label($project),
            ]);
    }

    /**
     * @return class-string<Project>
     */
    protected function getModelClass(): string
    {
        return Project::class;
    }

    protected function integration(): ProjectQueryIntegration
    {
        return $this->queryIntegration;
    }

    /**
     * Select-option queries do not expose list filters.
     *
     * @return array{}
     */
    public function searchableFields(string $scope): array
    {
        return [];
    }
}
