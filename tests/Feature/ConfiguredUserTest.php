<?php

namespace Projects13\Tests\Feature;

use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Database\Seeders\DevelopProjectSeeder;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ConfiguredUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_use_the_configured_user_model(): void
    {
        config()->set('auth.providers.users.model', Member::class);
        $member = Member::query()->create([
            'name' => 'Package author',
            'email' => 'member@example.test',
            'password' => 'password',
        ]);
        $this->actingAs($member);

        $comment = EpicComment::factory()->create();

        $this->assertSame($member->getKey(), $comment->user_id);
        $this->assertInstanceOf(Member::class, $comment->user);
        $this->assertSame('Package author', $comment->user->getAttribute('name'));
        $this->get(route('epics.index'))->assertOk();
    }

    public function test_development_comments_use_the_configured_user_provider(): void
    {
        config()->set('auth.providers.users.model', Member::class);
        $member = Member::query()->create([
            'name' => 'Package author',
            'email' => 'member@example.test',
            'password' => 'password',
        ]);

        app(DevelopProjectSeeder::class)->run(1);

        $this->assertDatabaseCount('PRO_epic_comments', 12);
        $this->assertSame([$member->getKey()], EpicComment::query()->distinct()->pluck('user_id')->all());
    }

    public function test_development_seeder_rejects_a_non_eloquent_auth_model(): void
    {
        config()->set('auth.providers.users.model', \stdClass::class);
        $this->expectException(\InvalidArgumentException::class);

        app(DevelopProjectSeeder::class)->run(1);
    }
}

class Member extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
