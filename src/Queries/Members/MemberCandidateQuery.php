<?php

namespace Projects13\Queries\Members;

use Projects13\Models\Project;
use Illuminate\Support\Collection;
use Basics13\Queries\ListQueryBase;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * The accounts an owner may add to a project: active, outside the trash, not members yet and never
 * the super-admin. Only the id and the name leave the query, since the owner may not be allowed to
 * see the user list itself.
 *
 * @extends ListQueryBase<Model>
 */
final class MemberCandidateQuery extends ListQueryBase
{
    use ResolvesUserModel;

    /**
     * @return Collection<int, array{id: int, text: string}>
     */
    public function search(Project $project, string $search): Collection
    {
        $pattern = $this->searchPattern($search);
        $modelClass = $this->userModelClass();

        return $modelClass::query()
            ->where('active', true)
            ->where('is_super_admin', false)
            ->whereNotIn('id', $project->members()->select('user_id'))
            ->when($search !== '', fn (Builder $query) => $query->whereRaw(
                'name like ? escape \''.self::LIKE_ESCAPE.'\'',
                [$pattern],
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::PER_PAGE)
            ->get(['id', 'name'])
            ->map(static fn (Model $user): array => [
                'id' => (int) $user->getKey(),
                'text' => (string) $user->getAttribute('name'),
            ]);
    }

    /**
     * @return class-string<Model>
     */
    protected function getModelClass(): string
    {
        return $this->userModelClass();
    }

    /**
     * Accounts have no integration, so the base hook gets a no-op that appends nothing.
     */
    protected function integration(): object
    {
        return new class
        {
            /**
             * @param  non-empty-list<literal-string>  $columns
             * @return non-empty-list<literal-string>
             */
            public function searchColumns(string $scope, array $columns): array
            {
                return $columns;
            }
        };
    }

    /**
     * The candidates are only ever searched by name: no advanced filters.
     *
     * @return array<string, array{column: string, label: string, type: string}>
     */
    public function searchableFields(string $scope): array
    {
        return [];
    }
}
