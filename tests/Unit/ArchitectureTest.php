<?php

namespace Projects13\Tests\Unit;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Basics13\Tests\ChecksResourceArchitecture;

class ArchitectureTest extends TestCase
{
    use ChecksResourceArchitecture;

    protected function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function resources(): array
    {
        return [
            'Project' => Project::class,
            'Epic' => Epic::class,
        ];
    }

    /**
     * EpicComment uses deleted_at and deleted_by for soft deletes; active remains reserved.
     */
    public function test_epic_comment_active_column_is_reserved_and_unused(): void
    {
        $comment = new EpicComment;

        $this->assertArrayNotHasKey('active', $comment->getCasts());
        $this->assertNotContains('active', $comment->getFillable());
    }
}
