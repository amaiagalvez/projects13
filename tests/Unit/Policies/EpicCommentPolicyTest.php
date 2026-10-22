<?php

namespace Projects13\Tests\Unit\Policies;

use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Projects13\Policies\EpicCommentPolicy;
use Basics13\Support\Testing\PermissionsActor;
use PHPUnit\Framework\Attributes\DataProvider;

class EpicCommentPolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function recordAbilities(): array
    {
        return [
            'update' => ['update', 'epics.edit'],
            'delete' => ['delete', 'epics.edit'],
            'forceDelete' => ['forceDelete', 'epics.edit'],
        ];
    }

    public function test_listing_and_creating_need_their_permission(): void
    {
        $policy = new EpicCommentPolicy;

        $this->assertTrue($policy->viewAny(PermissionsActor::holding(['epics.view'])));
        $this->assertFalse($policy->viewAny(PermissionsActor::holding([])));
        $this->assertTrue($policy->create(PermissionsActor::holding(['epics.edit'])));
        $this->assertFalse($policy->create(PermissionsActor::holding(['epics.view'])));
    }

    #[DataProvider('recordAbilities')]
    public function test_each_ability_on_a_record_needs_its_permission(string $ability, string $permission): void
    {
        $policy = new EpicCommentPolicy;

        $this->assertTrue($policy->{$ability}(PermissionsActor::holding([$permission]), new EpicComment));
        $this->assertFalse($policy->{$ability}(PermissionsActor::holding(['epics.view']), new EpicComment));
        $this->assertTrue($policy->{$ability}(PermissionsActor::holding(['epics.manage']), new EpicComment));
    }
}
