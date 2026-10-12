<?php

namespace Projects13\Contracts;

use Projects13\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Http\Requests\ProjectRequest;

/**
 * The one seam between `projects13` and whatever optional package knows about a project beyond
 * what this package can see on its own.
 *
 * `projects13` registers {@see NullProjectIntegration} by default, so every surface below degrades
 * to the plain project when no integration is installed: a project with a name, no extra rules, no
 * extra columns, and no extra rows. An integration package binds its own implementation in its
 * service provider, and this package stops knowing anything about it.
 *
 * Nothing here may reach for unvalidated request input: every value an implementation returns is
 * either something it validated in `rules()` or something it read off the models.
 */
interface ProjectIntegration
{
    /**
     * Rules to merge over the base project rules, keyed by field name.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(ProjectRequest $request): array;

    /**
     * Validation attribute names for the fields contributed by `rules()`, so the error summary
     * reads in the user's language instead of showing a raw field name.
     *
     * @return array<string, string>
     */
    public function attributes(): array;

    /**
     * Attributes to write on the project. Runs inside the same transaction that persists it, so an
     * implementation may lock whatever it validates here. Only return attributes this integration
     * validated in `rules()`; the caller writes them with `forceFill()` because they are not part
     * of the base model.
     *
     * @return array<string, mixed>
     */
    public function saving(ProjectRequest $request, ?Project $project): array;

    /**
     * Contribute joins to a project, epic, planning or timeline query. Called before the base
     * query searches or orders, so the columns this integration contributes in `searchColumns()`
     * and in the ordering already exist.
     *
     * @param  string  $scope  One of `project`, `epic`, `planning`, `timeline`.
     * @param  string  $alias  The alias the base query joins this scope under.
     * @param  Builder<covariant Model>  $query
     */
    public function scope(Builder $query, string $scope, string $alias): void;

    /**
     * Columns this integration contributes to the search of a scope, appended to the base ones.
     * The base search order must stay the same when the integration returns nothing.
     *
     * @param  string  $scope  One of `project`, `epic`, `planning`, `timeline`.
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function searchColumns(string $scope, array $columns): array;

    /**
     * How a project is named wherever it is picked: the epic form selector, the option the epic
     * list rehydrates after a validation error, and the epic edit payload.
     */
    public function label(Project $project): string;

    /**
     * What sits under a project row on the planning and the timeline board: the breadcrumb that
     * says who the row belongs to. Empty when nothing does, which is why a board row carries an
     * empty trail rather than the project's own name.
     *
     * @return list<string>
     */
    public function trail(Project $project): array;

    /**
     * Extra fields for a list row, merged over the base ones. The same fields are merged into the
     * edit drawer payload, so a row and a drawer reopened from it always carry the same keys.
     *
     * @param  Model  $record  A project or an epic.
     * @return array<string, mixed>
     */
    public function row(Model $record): array;

    /**
     * Whether the integration has active related entities that can be selected for a new project.
     * When false, the UI should offer to create one instead of showing an empty selector.
     */
    public function hasActiveRelatedEntities(): bool;

    /**
     * Orderings this integration contributes for a scope, appended to the base ones.
     * Called after the base query adds its orderings, so integration orderings are last.
     *
     * @param  string  $scope  One of `project`, `epic`, `planning`, `timeline`.
     * @param  string  $alias  The alias this scope is joined under.
     * @return list<string> Columns to order by, e.g. ['alias.name']
     */
    public function orderings(string $scope, string $alias): array;

    /**
     * Banner to show when there are no active related entities and the user can create a new project.
     * Returns null if no banner should be shown.
     *
     * @return array{message: string, action_label: string, action_url: string}|null
     */
    public function noRelatedEntitiesBanner(): ?array;
}
