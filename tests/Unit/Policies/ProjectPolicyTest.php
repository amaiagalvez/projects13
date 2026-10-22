<?php

namespace Projects13\Tests\Unit\Policies;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Policies\ProjectPolicy;
use Basics13\Support\Testing\PermissionsActor;
use PHPUnit\Framework\Attributes\DataProvider;

class ProjectPolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function recordAbilities(): array
    {
        return [
            'update' => ['update', 'projects.edit'],
            'delete' => ['delete', 'projects.delete'],
            'restore' => ['restore', 'projects.restore'],
            'forceDelete' => ['forceDelete', 'projects.force_delete'],
            'archive' => ['archive', 'projects.archive'],
            'activate' => ['activate', 'projects.archive'],
        ];
    }

    public function test_listing_and_creating_need_their_permission(): void
    {
        $policy = new ProjectPolicy;

        $this->assertTrue($policy->viewAny(PermissionsActor::holding(['projects.view'])));
        $this->assertFalse($policy->viewAny(PermissionsActor::holding([])));
        $this->assertTrue($policy->create(PermissionsActor::holding(['projects.create'])));
        $this->assertFalse($policy->create(PermissionsActor::holding(['projects.view'])));
    }

    #[DataProvider('recordAbilities')]
    public function test_each_ability_on_a_record_needs_its_permission(string $ability, string $permission): void
    {
        $policy = new ProjectPolicy;

        $this->assertTrue($policy->{$ability}(PermissionsActor::holding([$permission]), new Project));
        $this->assertFalse($policy->{$ability}(PermissionsActor::holding(['projects.view']), new Project));
        $this->assertTrue($policy->{$ability}(PermissionsActor::holding(['projects.manage']), new Project));
    }
}
