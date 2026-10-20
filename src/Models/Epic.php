<?php

namespace Projects13\Models;

use Carbon\CarbonImmutable;
use Projects13\Policies\EpicPolicy;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Basics13\Concerns\TransactionalDelete;
use Illuminate\Database\Eloquent\SoftDeletes;
use Projects13\Database\Factories\EpicFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property bool $active
 * @property int $project_id
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Project $project
 * @property-read int $comments_count
 */
#[Fillable(['name', 'notes', 'start_date', 'end_date', 'project_id'])]
#[UsePolicy(EpicPolicy::class)]
#[UseFactory(EpicFactory::class)]
class Epic extends Model
{
    protected $table = 'PRO_epics';

    /** @use HasFactory<EpicFactory> */
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return HasMany<EpicComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(EpicComment::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Epic $epic): bool {
            // Lock the epic row to prevent TOCTOU race
            $epic->lockForUpdate();

            // Prevent soft-deleting an epic that has comments
            return ! EpicComment::where('epic_id', $epic->id)->exists();
        });
    }
}
