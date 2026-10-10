<?php

namespace Projects13\Queries\Projects;

use Projects13\Models\Project;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectSelectOptionsQuery extends ListQueryBase
{
    /**
     * @return Collection<int, array{id: int, text: string}>
     */
    public function search(string $search): Collection
    {
        $pattern = $this->searchPattern($search);

        return Project::query()
            ->where('PRO_projects.active', true)
            ->with('customer:id,name')
            ->when($search !== '', function (Builder $query) use ($pattern): void {
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw(
                        'PRO_projects.name LIKE ? ESCAPE \''.self::LIKE_ESCAPE.'\'',
                        [$pattern],
                    )->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->whereRaw(
                        'CUM_customers.name LIKE ? ESCAPE \''.self::LIKE_ESCAPE.'\'',
                        [$pattern],
                    ));
                });
            })
            ->orderBy('PRO_projects.name')
            ->orderBy('PRO_projects.id')
            ->limit(self::PER_PAGE)
            ->get(['id', 'name', 'customer_id'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'text' => $project->fullName(),
            ]);
    }
}
