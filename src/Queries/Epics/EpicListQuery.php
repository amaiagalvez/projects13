<?php

namespace Projects13\Queries\Epics;

use Projects13\Models\Epic;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Contracts\ProjectIntegration;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListQueryBase<Epic>
 */
final class EpicListQuery extends ListQueryBase
{
    /**
     * Only the most recent comments are embedded in each list row; the total is in comments_count.
     */
    public const RECENT_COMMENTS_LIMIT = 20;

    public function __construct(private readonly ProjectIntegration $integration) {}

    /**
     * Epics without dates are listed after dated ones.
     *
     * @return LengthAwarePaginator<int, Epic>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->scoped($this->withProject(Epic::query()->where('PRO_epics.active', true)))
                ->orderByRaw('PRO_epics.start_date IS NULL')
                ->orderBy('PRO_epics.start_date')
                ->orderByRaw('PRO_epics.end_date IS NULL')
                ->orderBy('PRO_epics.end_date')
                ->orderBy('epic_projects.name')
                ->orderBy('PRO_epics.name')
                ->orderBy('PRO_epics.id'),
            $search,
            searchColumns: $this->searchColumns([
                'PRO_epics.name',
                'epic_projects.name',
                'PRO_epics.start_date',
                'PRO_epics.end_date',
            ]),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Epic>
     */
    public function archived(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->scoped($this->withProject(Epic::query()->where('PRO_epics.active', false)))
                ->orderByRaw('PRO_epics.start_date IS NULL')
                ->orderBy('PRO_epics.start_date')
                ->orderByRaw('PRO_epics.end_date IS NULL')
                ->orderBy('PRO_epics.end_date')
                ->orderBy('epic_projects.name')
                ->orderBy('PRO_epics.name')
                ->orderBy('PRO_epics.id'),
            $search,
            searchColumns: $this->searchColumns([
                'PRO_epics.name',
                'epic_projects.name',
                'PRO_epics.start_date',
                'PRO_epics.end_date',
                'PRO_epics.updated_at',
            ]),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Epic>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->scoped($this->withProject(Epic::onlyTrashed()))
                ->latest('PRO_epics.deleted_at')
                ->orderBy('PRO_epics.id'),
            $search,
            searchColumns: $this->searchColumns([
                'PRO_epics.name',
                'epic_projects.name',
                'PRO_epics.start_date',
                'PRO_epics.end_date',
                'PRO_epics.deleted_at',
            ]),
        );
    }

    public function findTrashedByName(string $name, int $projectId): ?Epic
    {
        $query = Epic::onlyTrashed()->where('project_id', $projectId);

        return $this->firstTrashedByName($query, $name);
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
        return $this->countStates(Epic::class, $activeTotal, $archivedTotal, $trashedTotal);
    }

    /**
     * Hand the query to the integration once the base join is in place, so the columns it
     * contributes to the search and to the ordering are already there.
     *
     * Epic names are unique per project and project names are unique among non-deleted records,
     * so project name plus epic name plus id already order this list completely: an integration
     * does not have to contribute an ordering to keep the rows grouped under their project.
     *
     * @param  Builder<Epic>  $query
     * @return Builder<Epic>
     */
    private function scoped(Builder $query): Builder
    {
        $this->integration->scope($query, 'epic', 'epic_customers');

        return $query;
    }

    /**
     * A row renders the name of the project it belongs to, so that is the only relation loaded
     * here, limited to the columns a name needs plus the keys to match it.
     *
     * @param  Builder<Epic>  $query
     * @return Builder<Epic>
     */
    private function withProject(Builder $query): Builder
    {
        return $query
            ->with('project:id,name')
            ->join('PRO_projects as epic_projects', 'epic_projects.id', '=', 'PRO_epics.project_id')
            ->select('PRO_epics.*')
            ->withCount('comments');
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
        $columns = $this->integration->searchColumns('epic', $columns);

        return $columns;
    }
}
