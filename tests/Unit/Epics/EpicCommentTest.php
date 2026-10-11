<?php

namespace Projects13\Tests\Unit\Epics;

use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;

class EpicCommentTest extends TestCase
{
    public function test_epic_comment_fields_can_be_mass_assigned(): void
    {
        $comment = new EpicComment;

        $comment->fill([
            'body' => 'Test comment body',
            'notes' => 'Test notes',
        ]);

        $this->assertSame('Test comment body', $comment->getAttributes()['body']);
        $this->assertSame('Test notes', $comment->getAttributes()['notes']);
    }

    public function test_epic_comment_belongs_to_epic_relation_defined(): void
    {
        $this->assertTrue(method_exists(EpicComment::class, 'epic') /** @phpstan-ignore-line function.alreadyNarrowedType */);
    }

    public function test_epic_comment_has_required_fields(): void
    {
        $comment = new EpicComment;

        $comment->fill([
            'body' => 'Required comment',
        ]);

        $this->assertEquals('Required comment', $comment->body);
    }
}
