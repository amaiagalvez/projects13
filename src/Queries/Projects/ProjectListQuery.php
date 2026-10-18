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
 */
final class ProjectListQuery extends ListQueryBase
{
    private const RESOURCE_PROJECT = 'project';

    /**
     * The alias the integration joins its entity table under. Like the scope above, it crosses
     * the integration on every call, so both live in a named constant.
     */
    private const ENTITY_ALIAS = 'project_entities';

    public function __construct(private readonly ProjectQueryIntegration $integration) {}

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function active(string $search): LengthAwarePaginator
    {
        $query = $this->scoped(Project::query()->where('PRO_projects.active', true))
            ->withExists(['epics' => fn(Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class)]);

        $this->applyOrderings($query);

        return $this->paginate(
            $this->withActiveCounts($query),
            $search,
            /** @phpstan-ignore argument.type */
            searchColumns: $this->searchColumns(self::RESOURCE_PROJECT, ['PRO_projects.name', 'PRO_projects.start_date', 'PRO_projects.end_date']),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function archived(string $search): LengthAwarePaginator
    {
        $query = $this->scoped(Project::query()->where('PRO_projects.active', false));

        $this->applyOrderings($query);

        return $this->paginate(
            $this->withActiveCounts($query),
            $search,
            /** @phpstan-ignore argument.type */
            searchColumns: $this->searchColumns(self::RESOURCE_PROJECT, [
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
            /** @phpstan-ignore argument.type */
            searchColumns: $this->searchColumns(self::RESOURCE_PROJECT, [
                'PRO_projects.name',
                'PRO_projects.start_date',
                'PRO_projects.end_date',
                'PRO_projects.deleted_at',
            ]),
        );
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

        $this->integration->scope($query, self::RESOURCE_PROJECT, self::ENTITY_ALIAS);

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
            'epics' => fn(Builder $epics) => $epics->where('PRO_epics.active', true),
            'comments' => fn(Builder $comments) => $comments->where('PRO_epics.active', true),
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

        foreach ($this->integration->orderings(self::RESOURCE_PROJECT, self::ENTITY_ALIAS) as $column) {
            $query->orderBy($column);
        }

        $query->orderBy('PRO_projects.name')
            ->orderBy('PRO_projects.id');
    }
}
