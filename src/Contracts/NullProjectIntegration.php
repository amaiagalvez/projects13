<?php

namespace Projects13\Contracts;

use Projects13\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Projects13\Http\Requests\ProjectRequest;

/**
 * What `projects13` does when no integration package is installed: a project is just its own
 * fields, named by its own name. Registered as the default binding so no consumer has to check
 * whether an integration exists.
 */
final class NullProjectIntegration implements ProjectIntegration
{
    /** @return array<string, array<int, mixed>> */
    public function rules(ProjectRequest $request): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function saving(ProjectRequest $request, ?Project $project): array
    {
        return [];
    }

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

    public function label(Project $project): string
    {
        return $project->name;
    }

    /** @return array<string, mixed> */
    public function row(Model $record): array
    {
        return [];
    }
}
