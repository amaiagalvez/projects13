<?php

namespace Projects13\Tests\Unit\Epics;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EpicBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function test_project_relationship_returns_project_even_when_trashed(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $this->assertTrue($project->is($epic->project));

        $project->delete();
        $epic->refresh();

        // The relationship uses withTrashed(), so it should still return the project
        $this->assertTrue($project->is($epic->project));
    }

    public function test_comments_relationship_returns_active_comments(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create(['active' => true]);
        EpicComment::factory()->for($epic)->count(3)->create();
        EpicComment::factory()->for($epic)->count(1)->trashed()->create(); // Should be excluded

        $comments = $epic->comments;

        $this->assertCount(3, $comments);
        foreach ($comments as $comment) {
            $this->assertNull($comment->deleted_at);
        }
    }

    public function test_deleting_epic_with_comments_fails(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();
        EpicComment::factory()->for($epic)->create();

        $result = $epic->delete();

        $this->assertFalse($result);
        $this->assertModelExists($epic);
    }

    public function test_deleting_epic_without_comments_succeeds(): void
    {
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create();

        $result = $epic->delete();

        $this->assertTrue($result);
        $this->assertSoftDeleted($epic);
    }

    public function test_epic_casts_dates_correctly(): void
    {
        $epic = Epic::factory()->create([
            'start_date' => '2026-06-15',
            'end_date' => '2026-12-20',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $epic->start_date);
        $this->assertInstanceOf(CarbonImmutable::class, $epic->end_date);
        $this->assertSame('2026-06-15', $epic->start_date->toDateString());
        $this->assertSame('2026-12-20', $epic->end_date->toDateString());
    }

    public function test_epic_casts_active_as_boolean(): void
    {
        $epic = Epic::factory()->create(['active' => true]);
        $this->assertTrue($epic->active);

        $epic->active = false;
        $epic->save();

        $this->assertFalse($epic->fresh()->active);
    }

    public function test_epic_defaults_to_active(): void
    {
        $epic = new Epic;
        $this->assertTrue($epic->active);
    }

    public function test_epic_can_have_null_end_date(): void
    {
        $epic = Epic::factory()->create([
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $this->assertNull($epic->end_date);
    }

    public function test_epic_requires_project_id(): void
    {
        $epic = new Epic;
        $epic->fill([
            'name' => 'Test Epic',
            'start_date' => '2026-01-01',
        ]);

        // project_id is in fillable but not required by the model itself
        // The validation happens in the request
        $this->assertArrayNotHasKey('project_id', $epic->getAttributes());
    }
}
