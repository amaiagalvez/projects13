<?php

namespace Projects13\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Projects13\Database\Factories\EpicCommentFactory;
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
