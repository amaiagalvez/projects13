<?php

namespace Projects13\Tests\Unit\Policies;

use Projects13\Models\Epic;
use Projects13\Tests\TestCase;
use Projects13\Policies\EpicPolicy;
use Basics13\Support\Testing\PermissionsActor;
use PHPUnit\Framework\Attributes\DataProvider;

class EpicPolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function recordAbilities(): array
    {
        return [
            'update' => ['update', 'epics.edit'],
            'comment' => ['comment', 'epics.edit'],
            'delete' => ['delete', 'epics.delete'],
            'restore' => ['restore', 'epics.restore'],
            'forceDelete' => ['forceDelete', 'epics.force_delete'],
            'archive' => ['archive', 'epics.archive'],
            'activate' => ['activate', 'epics.archive'],
        ];
    }

    public function test_listing_and_creating_need_their_permission(): void
    {
        $policy = new EpicPolicy;

        $this->assertTrue($policy->viewAny(PermissionsActor::holding(['epics.view'])));
        $this->assertFalse($policy->viewAny(PermissionsActor::holding([])));
        $this->assertTrue($policy->create(PermissionsActor::holding(['epics.create'])));
        $this->assertFalse($policy->create(PermissionsActor::holding(['epics.view'])));
    }

    #[DataProvider('recordAbilities')]
    public function test_each_ability_on_a_record_needs_its_permission(string $ability, string $permission): void
    {
        $policy = new EpicPolicy;

        $this->assertTrue($policy->{$ability}(PermissionsActor::holding([$permission]), new Epic));
        $this->assertFalse($policy->{$ability}(PermissionsActor::holding(['epics.view']), new Epic));
        $this->assertTrue($policy->{$ability}(PermissionsActor::holding(['epics.manage']), new Epic));
    }
}
