<?php

namespace Projects13\Tests\Feature;

use Projects13\Models\Epic;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Illuminate\Support\Facades\Gate;
use Projects13\Http\Requests\EpicCommentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Http\Controllers\EpicCommentController;

class EpicCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_uses_authenticated_author_and_reopens_drawer(): void
    {
        $user = $this->signIn();
        $epic = Epic::factory()->create();

        $this->post(route('epics.comments.store', $epic), ['body' => '  Ready for review  ', 'user_id' => 999])
            ->assertRedirect(route('epics.index'))
            ->assertSessionHas('commented_epic_id', $epic->id)
            ->assertSessionHas('status', __('Comment added successfully.'));

        $this->assertDatabaseHas('PRO_epic_comments', [
            'epic_id' => $epic->id, 'user_id' => $user->id, 'body' => 'Ready for review',
        ]);
    }

    public function test_blank_comment_uses_comment_error_bag(): void
    {
        $this->signIn();
        $epic = Epic::factory()->create();

        $this->post(route('epics.comments.store', $epic), ['body' => '   '])
            ->assertSessionHasErrorsIn('comment', 'body');

        $this->assertDatabaseCount('PRO_epic_comments', 0);
    }

    public function test_comment_permission_is_required(): void
    {
        $this->signIn();
        $epic = Epic::factory()->create();
        Gate::before(static fn (): bool => false);

        $this->post(route('epics.comments.store', $epic), ['body' => 'Ready for review'])->assertForbidden();

        $this->assertDatabaseCount('PRO_epic_comments', 0);
    }

    public function test_comment_is_rejected_if_the_epic_was_deleted_after_binding(): void
    {
        $epic = Epic::factory()->create();
        $epic->delete();

        $response = app(EpicCommentController::class)->store(new EpicCommentRequest, $epic);

        $this->assertSame(route('epics.index'), $response->getTargetUrl());
        $this->assertSame(__('Cannot comment on a deleted epic.'), $response->getSession()->get('error'));
        $this->assertDatabaseCount('PRO_epic_comments', 0);
    }

    public function test_comments_are_recent_first_limited_and_preserve_body(): void
    {
        $user = $this->signIn();
        $this->travelTo(now()->setDate(2026, 10, 11)->startOfDay());
        $epic = Epic::factory()->create();
        EpicComment::factory()->for($epic)->for($user)->count(21)->create(['created_at' => now()->subDay()]);
        $latest = EpicComment::factory()->for($epic)->for($user)->create(['body' => '<script>not executed</script>']);
        $other = Epic::factory()->create();
        EpicComment::factory()->for($other)->for($user)->create(['body' => 'Other epic']);

        $response = $this->get(route('epics.comments.index', $epic), ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonCount(20, 'comments')
            ->assertJsonPath('comments.0.id', $latest->id)
            ->assertJsonPath('comments.0.author', $user->name)
            ->assertJsonPath('comments.0.body', '<script>not executed</script>')
            ->assertJsonPath('comments.1.author', $user->name)
            ->assertJsonMissing(['body' => 'Other epic']);
    }
}
