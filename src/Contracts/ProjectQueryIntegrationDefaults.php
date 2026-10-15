<?php

namespace Projects13\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Default implementations for ProjectQueryIntegration.
 * Integration packages can use this trait to get empty default implementations.
 */
trait ProjectQueryIntegrationDefaults
{
    public function scope(Builder $query, string $scope, string $alias): void
    {
        //
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function searchColumns(string $scope, array $columns): array
    {
        return $columns;
    }

    /**
     * @return list<string>
     */
    public function orderings(string $scope, string $alias): array
    {
        return [];
    }
}
