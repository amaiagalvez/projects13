<?php

namespace Projects13\Queries\Projects;

use Projects13\Models\Project;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Projects13\Contracts\ProjectIntegration;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectSelectOptionsQuery extends ListQueryBase
{
    public function __construct(private readonly ProjectIntegration $integration) {}

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

        $this->integration->scope($query, 'project', 'project_customers');

        /** @var non-empty-list<literal-string> $columns An integration appends to the base column; it never replaces it. */
        $columns = $this->integration->searchColumns('project', ['PRO_projects.name']);

        $this->whereMatches($query, $search, $columns);

        return $query
            ->orderBy('PRO_projects.name')
            ->orderBy('PRO_projects.id')
            ->limit(self::PER_PAGE)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'text' => $this->integration->label($project),
            ]);
    }
}
