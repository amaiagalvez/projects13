<?php

namespace Projects13\Queries\Epics;

use Projects13\Models\Epic;
use Customers13\Models\Customer;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
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

    private const SEARCH_COLUMNS = [
        'PRO_epics.name',
        'epic_projects.name',
        'epic_customers.name',
        'PRO_epics.start_date',
        'PRO_epics.end_date',
    ];

    /**
     * Epics without dates are listed after dated ones.
     *
     * @return LengthAwarePaginator<int, Epic>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withProjectAndCustomer(Epic::query()->where('PRO_epics.active', true))
                ->orderByRaw('PRO_epics.start_date IS NULL')
                ->orderBy('PRO_epics.start_date')
                ->orderByRaw('PRO_epics.end_date IS NULL')
                ->orderBy('PRO_epics.end_date')
                ->orderBy('epic_projects.name')
                ->orderBy('epic_customers.name')
                ->orderBy('PRO_epics.name')
                ->orderBy('PRO_epics.id'),
            $search,
            searchColumns: self::SEARCH_COLUMNS,
        );
    }

    /**
     * @return LengthAwarePaginator<int, Epic>
     */
    public function archived(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withProjectAndCustomer(Epic::query()->where('PRO_epics.active', false))
                ->orderByRaw('PRO_epics.start_date IS NULL')
                ->orderBy('PRO_epics.start_date')
                ->orderByRaw('PRO_epics.end_date IS NULL')
                ->orderBy('PRO_epics.end_date')
                ->orderBy('epic_projects.name')
                ->orderBy('epic_customers.name')
                ->orderBy('PRO_epics.name')
                ->orderBy('PRO_epics.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'PRO_epics.updated_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Epic>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withProjectAndCustomer(Epic::onlyTrashed())
                ->latest('PRO_epics.deleted_at')
                ->orderBy('PRO_epics.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'PRO_epics.deleted_at'],
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
     * A row only renders the project name and the name of its customer, so both eager loads are
     * limited to those columns plus the keys needed to match the relations.
     *
     * @param  Builder<Epic>  $query
     * @return Builder<Epic>
     */
    private function withProjectAndCustomer(Builder $query): Builder
    {
        return $query
            ->with(['project:id,name,customer_id', 'project.customer:id,name'])
            ->join('PRO_projects as epic_projects', 'epic_projects.id', '=', 'PRO_epics.project_id')
            ->join((new Customer)->getTable().' as epic_customers', 'epic_customers.id', '=', 'epic_projects.customer_id')
            ->select('PRO_epics.*')
            ->withCount('comments');
    }
}
