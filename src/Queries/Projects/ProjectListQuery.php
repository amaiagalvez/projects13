<?php

namespace Projects13\Queries\Projects;

use Projects13\Models\Project;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Contracts\ProjectIntegration;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectListQuery extends ListQueryBase
{
    public function __construct(private readonly ProjectIntegration $integration) {}

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts(
                $this->scoped(Project::query()->where('PRO_projects.active', true))
                    ->withExists(['epics' => fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class)])
            )
                ->orderBy('PRO_projects.start_date')
                ->orderBy('PRO_projects.end_date')
                ->orderBy('PRO_projects.name')
                ->orderBy('PRO_projects.id'),
            $search,
            searchColumns: $this->searchColumns(['PRO_projects.name', 'PRO_projects.start_date', 'PRO_projects.end_date']),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function archived(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->scoped(Project::query()->where('PRO_projects.active', false)))
                ->orderBy('PRO_projects.start_date')
                ->orderBy('PRO_projects.end_date')
                ->orderBy('PRO_projects.name')
                ->orderBy('PRO_projects.id'),
            $search,
            searchColumns: $this->searchColumns([
                'PRO_projects.name',
                'PRO_projects.start_date',
                'PRO_projects.end_date',
                'PRO_projects.updated_at',
            ]),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->scoped(Project::onlyTrashed()))
                ->latest('PRO_projects.deleted_at')
                ->orderBy('PRO_projects.id'),
            $search,
            searchColumns: $this->searchColumns([
                'PRO_projects.name',
                'PRO_projects.start_date',
                'PRO_projects.end_date',
                'PRO_projects.deleted_at',
            ]),
        );
    }

    public function findTrashedByName(string $name): ?Project
    {
        return $this->firstTrashedByName(Project::onlyTrashed(), $name);
    }

    /**
     * Number of records in each list state, shown as the badge of the state tabs.
     *
     * @return array{active: int, archived: int, trashed: int}
     */
    public function stateCounts(
        ?int $activeTotal = null,
        ?int $archivedTotal = null,
        ?int $trashedTotal = null,
    ): array {
        return $this->countStates(Project::class, $activeTotal, $archivedTotal, $trashedTotal);
    }

    /**
     * Hand the query to the integration before anything searches or orders it, so the columns it
     * contributes to the search and to the ordering are already there.
     *
     * Project names are unique among non-deleted records, so name plus id already order this list
     * completely: there is no tie left for any further column to break, and an integration does
     * not have to contribute an ordering to keep the rows grouped the way they were.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function scoped(Builder $query): Builder
    {
        // Selected explicitly: once an integration joins another table in, a bare select would let
        // that table's columns overwrite this one's on hydration.
        $query->select('PRO_projects.*');

        $this->integration->scope($query, 'project', 'project_customers');

        return $query;
    }

    /**
     * Only active epics and the comments written on them are counted; deleted ones are excluded by
     * the relations.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function withActiveCounts(Builder $query): Builder
    {
        return $query->withCount([
            'epics' => fn (Builder $epics) => $epics->where('PRO_epics.active', true),
            'comments' => fn (Builder $comments) => $comments->where('PRO_epics.active', true),
        ]);
    }

    /**
     * Base search columns for this list, with whatever the integration contributes appended.
     *
     * @param  non-empty-list<literal-string>  $columns
     * @return non-empty-list<literal-string>
     */
    private function searchColumns(array $columns): array
    {
        /** @var non-empty-list<literal-string> $columns An integration appends to the base columns; it never replaces them. */
        $columns = $this->integration->searchColumns('project', $columns);

        return $columns;
    }
}
