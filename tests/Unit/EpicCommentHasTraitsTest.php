<?php

namespace Projects13\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Projects13\Models\EpicComment;
use PHPUnit\Framework\Attributes\Test;
use Basics13\Concerns\TracksAuditColumns;
use Basics13\Concerns\TransactionalDelete;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Verifies that EpicComment model has the required traits for model parity
 * with other resource models (Entity, Project, Epic).
 *
 * @see https://github.com/amaia/l13/issues/CRITICAL-001
 */
class EpicCommentHasTraitsTest extends TestCase
{
    #[Test]
    public function epic_comment_has_soft_deletes_trait(): void
    {
        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(EpicComment::class),
            'EpicComment must use SoftDeletes trait',
        );
    }

    #[Test]
    public function epic_comment_has_tracks_audit_columns_trait(): void
    {
        $this->assertContains(
            TracksAuditColumns::class,
            class_uses_recursive(EpicComment::class),
            'EpicComment must use TracksAuditColumns trait',
        );
    }

    #[Test]
    public function epic_comment_has_transactional_delete_trait(): void
    {
        $this->assertContains(
            TransactionalDelete::class,
            class_uses_recursive(EpicComment::class),
            'EpicComment must use TransactionalDelete trait',
        );
    }

    #[Test]
    public function epic_comment_has_all_required_traits(): void
    {
        $traits = class_uses_recursive(EpicComment::class);
        $required = [SoftDeletes::class, TransactionalDelete::class, TracksAuditColumns::class];

        foreach ($required as $trait) {
            $this->assertContains(
                $trait,
                $traits,
                "EpicComment must use {$trait} trait",
            );
        }
    }
}
