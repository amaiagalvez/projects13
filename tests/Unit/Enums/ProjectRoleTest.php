<?php

namespace Projects13\Tests\Unit\Enums;

use Projects13\Tests\TestCase;
use Projects13\Enums\ProjectRole;

class ProjectRoleTest extends TestCase
{
    public function test_edits_returns_true_for_owner_and_editor(): void
    {
        $this->assertTrue(ProjectRole::Owner->edits());
        $this->assertTrue(ProjectRole::Editor->edits());
        $this->assertFalse(ProjectRole::Viewer->edits());
    }

    public function test_label_returns_translated_string(): void
    {
        app()->setLocale('en');

        $this->assertSame('Owner', ProjectRole::Owner->label());
        $this->assertSame('Editor', ProjectRole::Editor->label());
        $this->assertSame('Viewer', ProjectRole::Viewer->label());
    }

    public function test_values_are_correct(): void
    {
        $this->assertSame('owner', ProjectRole::Owner->value);
        $this->assertSame('editor', ProjectRole::Editor->value);
        $this->assertSame('viewer', ProjectRole::Viewer->value);
    }

    public function test_cases_exist(): void
    {
        $cases = ProjectRole::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(ProjectRole::Owner, $cases);
        $this->assertContains(ProjectRole::Editor, $cases);
        $this->assertContains(ProjectRole::Viewer, $cases);
    }
}
