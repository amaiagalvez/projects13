<?php

namespace Projects13\Models;

use Carbon\CarbonImmutable;
use Projects13\Policies\ProjectPolicy;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Basics13\Concerns\TransactionalDelete;
use Illuminate\Database\Eloquent\SoftDeletes;
use Projects13\Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property bool $active
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read bool $epics_exists
 * @property-read int $epics_count
 * @property-read int $comments_count
 */
#[Fillable(['name', 'notes', 'start_date', 'end_date'])]
#[UsePolicy(ProjectPolicy::class)]
#[UseFactory(ProjectFactory::class)]
class Project extends Model
{
    protected $table = 'PRO_projects';

    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes, TracksAuditColumns, TransactionalDelete;

    /**
     * @var array{active: bool}
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * @return array{start_date: 'date', end_date: 'date', active: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Epic, $this>
     */
    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class);
    }

    /**
     * The users the project is shared with, each one with a role. Without `projects.manage` a user
     * only sees the projects they are a member of.
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Comments written on the epics of this project; trashed epics are excluded.
     *
     * @return HasManyThrough<EpicComment, Epic, $this>
     */
    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(EpicComment::class, Epic::class, 'project_id', 'epic_id');
    }
}
