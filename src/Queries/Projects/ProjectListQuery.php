<?php

namespace Projects13\Queries\Projects;

use Projects13\Models\Project;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectQueryIntegration;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @extends ListQueryBase<Project>
 *
 * @phpstan-import-type SearchFields from \Basics13\Queries\ListQueryBase
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class ProjectListQuery extends ListQueryBase
{
    private const RESOURCE_PROJECT = 'project';

    private const SCOPE_ACTIVE = 'active';

    private const SCOPE_ARCHIVED = 'archived';

    private const SCOPE_TRASHED = 'trashed';

    /**
     * The alias the integration joins its related table under. Like the scope above, it crosses
     * the integration on every call, so both live in a named constant.
     */
    private const RELATED_ALIAS = 'project_related';

    public function __construct(private readonly ProjectQueryIntegration $integration) {}

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function active(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->baseQuery(self::SCOPE_ACTIVE)),
            $search,
            searchColumns: $this->searchColumnsFor(self::SCOPE_ACTIVE),
            filters: $filters,
            scope: self::SCOPE_ACTIVE,
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function archived(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->baseQuery(self::SCOPE_ARCHIVED)),
            $search,
            searchColumns: $this->searchColumnsFor(self::SCOPE_ARCHIVED),
            filters: $filters,
            scope: self::SCOPE_ARCHIVED,
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function trashed(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withActiveCounts($this->baseQuery(self::SCOPE_TRASHED)),
            $search,
            searchColumns: $this->searchColumnsFor(self::SCOPE_TRASHED),
            filters: $filters,
            scope: self::SCOPE_TRASHED,
        );
    }

    /**
     * Get the searchable field definitions for the current scope.
     *
     * @param  string  $scope  One of 'active', 'archived', 'trashed'
     * @return SearchFields
     */
    public function searchableFields(string $scope): array
    {
        $fields = [
            'name' => [
                'column' => 'PRO_projects.name',
                'label' => 'Name',
                'type' => 'text',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
            'notes' => [
                'column' => 'PRO_projects.notes',
                'label' => 'Notes',
                'type' => 'text',
                'operators' => ['contains', 'equals', 'not_equals'], // Excludes starts_with/ends_with
                'visible' => false,
                'order' => 20,
                'group' => 'basic',
            ],
            'start_date' => [
                'column' => 'PRO_projects.start_date',
                'label' => 'Start date',
                'type' => 'date',
                'nullable' => false,
                'visible' => true,
                'order' => 10,
                'group' => 'dates',
            ],
            'end_date' => [
                'column' => 'PRO_projects.end_date',
                'label' => 'End date',
                'type' => 'date',
                'nullable' => true,
                'visible' => true,
                'order' => 20,
                'group' => 'dates',
            ],
            // Only the trash mixes active and archived records: on the active and archived lists
            // the status filter could only return everything or nothing.
            ...($scope === 'trashed' ? ['active' => [
                'column' => 'PRO_projects.active',
                'label' => 'Status',
                'type' => 'boolean',
                'visible' => true,
                'order' => 10,
                'group' => 'status',
            ]] : []),
            'created_at' => [
                'column' => 'PRO_projects.created_at',
                'label' => 'basics13::messages.created_at',
                'type' => 'date',
                'visible' => true,
                'order' => 30,
                'group' => 'dates',
            ],
        ];

        return [...$fields, ...$this->integration()->searchableFields($scope)];
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
        return $this->integration;
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

        $this->integration->scope($query, self::RESOURCE_PROJECT, self::RELATED_ALIAS);

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
     * Apply all orderings for the project list: base then integration.
     *
     * @param  Builder<Project>  $query
     */
    private function applyOrderings(Builder $query): void
    {
        $query->orderBy('PRO_projects.start_date')
            ->orderBy('PRO_projects.end_date');

        foreach ($this->integration->orderings(self::RESOURCE_PROJECT, self::RELATED_ALIAS) as $column) {
            $query->orderBy($column);
        }

        $query->orderBy('PRO_projects.name')
            ->orderBy('PRO_projects.id');
    }

    /**
     * Build the base query for a list scope: joins, status and ordering, without the counts the
     * listing displays. The export skips counts it does not show.
     *
     * @return Builder<Project>
     */
    private function baseQuery(string $scope): Builder
    {
        $query = match ($scope) {
            self::SCOPE_ARCHIVED => $this->scoped(Project::query()->where('PRO_projects.active', false)),
            self::SCOPE_TRASHED => $this->scoped(Project::onlyTrashed())
                ->latest('PRO_projects.deleted_at')
                ->orderBy('PRO_projects.id'),
            default => $this->scoped(Project::query()->where('PRO_projects.active', true))
                ->withExists(['epics' => fn (Builder $q) => $q->withoutGlobalScope(SoftDeletingScope::class)]),
        };

        // The trash orders by deletion date, not by the project columns.
        if ($scope !== self::SCOPE_TRASHED) {
            $this->applyOrderings($query);
        }

        return $query;
    }

    /**
     * Search columns for a list scope, with whatever the integration contributes appended.
     *
     * @return non-empty-list<literal-string>
     */
    private function searchColumnsFor(string $scope): array
    {
        return $this->searchColumns(self::RESOURCE_PROJECT, match ($scope) {
            self::SCOPE_ARCHIVED => ['PRO_projects.name', 'PRO_projects.start_date', 'PRO_projects.end_date', 'PRO_projects.updated_at'],
            self::SCOPE_TRASHED => ['PRO_projects.name', 'PRO_projects.start_date', 'PRO_projects.end_date', 'PRO_projects.deleted_at'],
            default => ['PRO_projects.name', 'PRO_projects.start_date', 'PRO_projects.end_date'],
        });
    }

    /**
     * The list query without pagination, for the Excel export: the same scope, search and filters
     * the page is showing.
     *
     * @param  SearchFilters  $filters
     * @return Builder<Project>
     */
    public function filteredQuery(string $search, array $filters = [], string $scope = self::SCOPE_ACTIVE): Builder
    {
        return $this->filtered($this->baseQuery($scope), $search, $this->searchColumnsFor($scope), $filters, $scope);
    }
}
