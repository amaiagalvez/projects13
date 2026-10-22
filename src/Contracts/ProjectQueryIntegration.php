<?php

namespace Projects13\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query seam: scope joins, search columns, orderings and advanced search fields.
 *
 * @phpstan-import-type SearchFields from \Basics13\Queries\ListQueryBase
 */
interface ProjectQueryIntegration
{
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
     * Orderings this integration contributes for a scope, appended to the base ones.
     * Called after the base query adds its orderings, so integration orderings are last.
     *
     * @param  string  $scope  One of `project`, `epic`, `planning`, `timeline`.
     * @param  string  $alias  The alias this scope is joined under.
     * @return list<string> Columns to order by, e.g. ['alias.name']
     */
    public function orderings(string $scope, string $alias): array;

    /**
     * Fields this integration contributes to the advanced search of the project list, appended to
     * the base ones. They are built on each call rather than kept in the configuration, so their
     * `options` and `condition` callables never stop `config:cache` from serializing it.
     *
     * @param  string  $scope  One of `active`, `archived`, `trashed`.
     * @return SearchFields
     */
    public function searchableFields(string $scope): array;
}
