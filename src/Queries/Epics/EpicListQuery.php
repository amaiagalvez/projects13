<?php

namespace Projects13\Queries\Epics;

use Projects13\Models\Epic;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectQueryIntegration;

/**
 * @extends ListQueryBase<Epic>
 *
 * @phpstan-import-type SearchFilters from ListQueryBase
 */
final class EpicListQuery extends ListQueryBase
{
    /**
     * Only the most recent comments are embedded in each list row; the total is in comments_count.
     */
    public const RECENT_COMMENTS_LIMIT = 20;

    private const RESOURCE_EPIC = 'epic';

    /**
     * The alias the integration joins its related table under. Like the scope above, it crosses
     * the integration on every call, so both live in a named constant.
     */
    private const RELATED_ALIAS = 'epic_related';

    public function __construct(private readonly ProjectQueryIntegration $integration) {}

    /**
     * Epics without dates are listed after dated ones.
     *
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Epic>
     */
    public function active(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withIntegrationOrderings(
                $this->scoped($this->withProject(Epic::query()->where('PRO_epics.active', true)))
                    ->orderByRaw('PRO_epics.start_date IS NULL')
                    ->orderBy('PRO_epics.start_date')
                    ->orderByRaw('PRO_epics.end_date IS NULL')
                    ->orderBy('PRO_epics.end_date')
                    ->orderBy('epic_projects.name')
                    ->orderBy('PRO_epics.name')
                    ->orderBy('PRO_epics.id'),
            ),
            $search,
            /** @phpstan-ignore argument.type */
            searchColumns: $this->searchColumns(self::RESOURCE_EPIC, [
                'PRO_epics.name',
                'epic_projects.name',
                'PRO_epics.start_date',
                'PRO_epics.end_date',
            ]),
            filters: $filters,
            scope: 'active',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Epic>
     */
    public function archived(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->withIntegrationOrderings(
                $this->scoped($this->withProject(Epic::query()->where('PRO_epics.active', false)))
                    ->orderByRaw('PRO_epics.start_date IS NULL')
                    ->orderBy('PRO_epics.start_date')
                    ->orderByRaw('PRO_epics.end_date IS NULL')
                    ->orderBy('PRO_epics.end_date')
                    ->orderBy('epic_projects.name')
                    ->orderBy('PRO_epics.name')
                    ->orderBy('PRO_epics.id'),
            ),
            $search,
            /** @phpstan-ignore argument.type */
            searchColumns: $this->searchColumns(self::RESOURCE_EPIC, [
                'PRO_epics.name',
                'epic_projects.name',
                'PRO_epics.start_date',
                'PRO_epics.end_date',
                'PRO_epics.updated_at',
            ]),
            filters: $filters,
            scope: 'archived',
        );
    }

    /**
     * @param  SearchFilters  $filters
     * @return LengthAwarePaginator<int, Epic>
     */
    public function trashed(string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->scoped($this->withProject(Epic::onlyTrashed()))
                ->latest('PRO_epics.deleted_at')
                ->orderBy('PRO_epics.id'),
            $search,
            /** @phpstan-ignore argument.type */
            searchColumns: $this->searchColumns(self::RESOURCE_EPIC, [
                'PRO_epics.name',
                'epic_projects.name',
                'PRO_epics.start_date',
                'PRO_epics.end_date',
                'PRO_epics.deleted_at',
            ]),
            filters: $filters,
            scope: 'trashed',
        );
    }

    /**
     * Epic names are unique inside their project, so the trashed epic a new one would collide with
     * is looked up inside the same project.
     *
     * @return Epic|null
     */
    public function findTrashedByName(string $name, ?int $projectId = null): ?Model
    {
        $query = $this->trashedQuery();

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $this->firstTrashedByName($query, $name);
    }

    /**
     * Get the searchable field definitions for the current scope.
     *
     * @param  string  $scope  One of 'active', 'archived', 'trashed'
     * @return array<string, array{column: string, label: string, type: string, operators?: array<int,string>, default_operator?: string, visible?: bool, order?: int, group?: string, nullable?: bool, placeholder?: string, help?: string, condition?: callable, options?: array<int,string>|callable, multiple?: bool}>
     */
    public function searchableFields(string $scope): array
    {
        return [
            'name' => [
                'column' => 'PRO_epics.name',
                'label' => 'Name',
                'type' => 'text',
                'visible' => true,
                'order' => 10,
                'group' => 'basic',
            ],
            'notes' => [
                'column' => 'PRO_epics.notes',
                'label' => 'Notes',
                'type' => 'text',
                'operators' => ['contains', 'equals', 'not_equals'], // Excludes starts_with/ends_with
                'visible' => false,
                'order' => 20,
                'group' => 'basic',
            ],
            'start_date' => [
                'column' => 'PRO_epics.start_date',
                'label' => 'Start date',
                'type' => 'date',
                'nullable' => true,
                'visible' => true,
                'order' => 10,
                'group' => 'dates',
            ],
            'end_date' => [
                'column' => 'PRO_epics.end_date',
                'label' => 'End date',
                'type' => 'date',
                'nullable' => true,
                'visible' => true,
                'order' => 20,
                'group' => 'dates',
            ],
            'active' => [
                'column' => 'PRO_epics.active',
                'label' => 'Status',
                'type' => 'boolean',
                'visible' => true,
                'order' => 10,
                'group' => 'status',
            ],
            'created_at' => [
                'column' => 'PRO_epics.created_at',
                'label' => 'basics13::messages.created_at',
                'type' => 'date',
                'visible' => true,
                'order' => 30,
                'group' => 'dates',
            ],
            'project_name' => [
                'column' => 'epic_projects.name',
                'label' => 'Project',
                'type' => 'text',
                'visible' => true,
                'order' => 10,
                'group' => 'relations',
                'condition' => fn (string $scope) => ! request()->route('project'),
            ],
        ];
    }

    /**
     * @return class-string<Epic>
     */
    protected function getModelClass(): string
    {
        return Epic::class;
    }

    protected function integration(): ProjectQueryIntegration
    {
        return $this->integration;
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
        $this->integration->scope($query, self::RESOURCE_EPIC, self::RELATED_ALIAS);

        return $query;
    }

    /**
     * A row renders the project name and its edit payload needs the active state, so those are the
     * only relation columns loaded here, plus the keys to match it.
     *
     * @param  Builder<Epic>  $query
     * @return Builder<Epic>
     */
    private function withProject(Builder $query): Builder
    {
        return $query
            ->with('project:id,name,active')
            ->join('PRO_projects as epic_projects', 'epic_projects.id', '=', 'PRO_epics.project_id')
            ->select('PRO_epics.*')
            ->withCount('comments');
    }

    /**
     * Apply integration-specific orderings to the query.
     *
     * @param  Builder<Epic>  $query
     * @return Builder<Epic>
     */
    private function withIntegrationOrderings(Builder $query): Builder
    {
        foreach ($this->integration->orderings(self::RESOURCE_EPIC, self::RELATED_ALIAS) as $column) {
            $query->orderBy($column);
        }

        return $query;
    }
}
