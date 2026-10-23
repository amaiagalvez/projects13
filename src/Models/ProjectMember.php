<?php

namespace Projects13\Models;

use Carbon\CarbonImmutable;
use Projects13\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Projects13\Database\Factories\ProjectMemberFactory;

/**
 * A user's membership of a project and the role it gives them there. `project_id` and `added_by` are
 * set by whoever adds the member, never from a request.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property ProjectRole $role
 * @property int|null $added_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'role'])]
#[UseFactory(ProjectMemberFactory::class)]
class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    protected $table = 'PRO_project_members';

    /**
     * @return array{role: class-string<ProjectRole>}
     */
    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = config()->string('auth.providers.users.model');

        if (! is_subclass_of($userModel, Model::class)) {
            throw new \InvalidArgumentException('The users authentication provider must use an Eloquent model.');
        }

        return $this->belongsTo($userModel, 'user_id');
    }
}
