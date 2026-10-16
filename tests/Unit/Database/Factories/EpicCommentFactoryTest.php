<?php

namespace Projects13\Tests\Unit\Database\Factories;

use App\Models\User;
use Projects13\Models\Epic;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Projects13\Database\Factories\EpicCommentFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

class EpicCommentFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_epic_comment_factory_creates_valid_comment(): void
    {
        $comment = EpicComment::factory()->createOne();

        $this->assertInstanceOf(EpicComment::class, $comment);
        $this->assertNotEmpty($comment->body);
        $this->assertGreaterThan(0, $comment->user_id);
        $this->assertGreaterThan(0, $comment->epic_id);
        $this->assertDatabaseHas('PRO_epic_comments', ['id' => $comment->id]);
    }

    public function test_epic_comment_factory_belongs_to_epic(): void
    {
        $epic = Epic::factory()->createOne();
        $comment = EpicComment::factory()->for($epic)->createOne();

        $this->assertEquals($epic->id, $comment->epic_id);
        $this->assertInstanceOf(Epic::class, $comment->epic);
    }

    public function test_epic_comment_factory_belongs_to_user(): void
    {
        $user = User::factory()->createOne();
        $comment = EpicComment::factory()->for($user, 'user')->createOne();

        $this->assertEquals($user->id, $comment->user_id);
        $this->assertInstanceOf(User::class, $comment->user);
    }

    public function test_epic_comment_factory_get_record_returns_existing_user_when_available(): void
    {
        $existing = User::factory()->create();

        $user = EpicCommentFactory::getRecordOrNew(User::class);

        $this->assertSame($existing->id, $user->id);
    }

    public function test_epic_comment_factory_get_record_creates_new_user_when_none_exists(): void
    {
        $user = EpicCommentFactory::getRecordOrNew(User::class);

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_epic_comment_factory_get_record_returns_existing_epic_when_available(): void
    {
        $existing = Epic::factory()->create();

        $epic = EpicCommentFactory::getRecordOrNew(Epic::class);

        $this->assertSame($existing->id, $epic->id);
    }

    public function test_epic_comment_factory_get_record_creates_new_epic_when_none_exists(): void
    {
        $epic = EpicCommentFactory::getRecordOrNew(Epic::class);

        $this->assertInstanceOf(Epic::class, $epic);
        $this->assertDatabaseHas('PRO_epics', ['id' => $epic->id]);
    }

    public function test_epic_comment_factory_definition_returns_valid_data(): void
    {
        $factory = new class extends EpicCommentFactory
        {
            /** @return array{body: string, user_id: User, epic_id: Epic} */
            public function callDefinition(): array
            {
                /** @var array{body: string, user_id: User, epic_id: Epic} $def */
                $def = $this->definition();

                return $def;
            }
        };

        $definition = $factory->callDefinition();

        $this->assertArrayHasKey('body', $definition);
        $this->assertArrayHasKey('user_id', $definition);
        $this->assertArrayHasKey('epic_id', $definition);
        $this->assertNotEmpty($definition['body']);
        $this->assertInstanceOf(User::class, $definition['user_id']);
        $this->assertInstanceOf(Epic::class, $definition['epic_id']);
    }
}
