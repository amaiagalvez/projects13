<?php

namespace Projects13\Queries\Projects;

use Projects13\Models\Project;
use Customers13\Models\Customer;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectListQuery extends ListQueryBase
{
    private const SEARCH_COLUMNS = [
        'PRO_projects.name',
        'project_customers.name',
        'PRO_projects.start_date',
        'PRO_projects.end_date',
    ];

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function active(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->withCustomer(Project::query()->where('PRO_projects.active', true)))
                ->withExists(['epics' => fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class)])
                ->orderBy('PRO_projects.start_date')
                ->orderBy('PRO_projects.end_date')
                ->orderBy('project_customers.name')
                ->orderBy('PRO_projects.name')
                ->orderBy('PRO_projects.id'),
            $search,
            searchColumns: self::SEARCH_COLUMNS,
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function archived(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->withCustomer(Project::query()->where('PRO_projects.active', false)))
                ->orderBy('PRO_projects.start_date')
                ->orderBy('PRO_projects.end_date')
                ->orderBy('project_customers.name')
                ->orderBy('PRO_projects.name')
                ->orderBy('PRO_projects.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'PRO_projects.updated_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function trashed(string $search): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->withCustomer(Project::onlyTrashed()))
                ->latest('PRO_projects.deleted_at')
                ->orderBy('PRO_projects.id'),
            $search,
            searchColumns: [...self::SEARCH_COLUMNS, 'PRO_projects.deleted_at'],
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
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private function withCustomer(Builder $query): Builder
    {
        return $query
            ->with('customer:id,name')
            ->join((new Customer)->getTable().' as project_customers', 'project_customers.id', '=', 'PRO_projects.customer_id')
            ->select('PRO_projects.*');
    }
}
