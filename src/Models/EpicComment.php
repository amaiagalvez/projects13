<?php

namespace Projects13\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Projects13\Policies\EpicCommentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Projects13\Database\Factories\EpicCommentFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * Comments are created through Epic's comment ability; they have no independent mutation policy.
 *
 * @property int $id
 * @property int $epic_id
 * @property int $user_id
 * @property string $body
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['body', 'notes'])]
#[UseFactory(EpicCommentFactory::class)]
#[UsePolicy(EpicCommentPolicy::class)]
class EpicComment extends Model
{
    protected $table = 'PRO_epic_comments';

    /** @use HasFactory<EpicCommentFactory> */
    use HasFactory, TracksAuditColumns;

    /**
     * @return BelongsTo<Epic, $this>
     */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class)->withTrashed();
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
