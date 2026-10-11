<?php

namespace Projects13\Tests\Feature;

use Illuminate\View\View;
use Projects13\Models\Epic;
use Illuminate\Session\Store;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Customers13\Models\Customer;
use Projects13\Models\EpicComment;
use Projects13\Queries\Epics\EpicListQuery;
use Projects13\Http\Requests\EpicListRequest;
use Projects13\Http\Controllers\EpicController;
use Projects13\Http\Requests\ProjectListRequest;
use Projects13\Transformers\EpicListTransformer;
use Projects13\Queries\Projects\ProjectListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Projects13\Http\Controllers\ProjectController;
use Projects13\Transformers\ProjectListTransformer;
use Projects13\Http\Controllers\EpicTrashController;
use Projects13\Http\Controllers\EpicArchivedController;
use Projects13\Http\Controllers\ProjectTrashController;
use Projects13\Http\Controllers\ProjectArchivedController;

class ResourceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_lists_partition_states_and_offer_correct_actions(): void
    {
        $user = $this->signIn();
        $customer = Customer::factory()->create(['name' => 'North customer']);
        $project = Project::factory()->for($customer)->create(['name' => 'North delivery']);
        $empty = Project::factory()->for($customer)->create(['name' => 'Empty delivery', 'end_date' => null]);
        $archived = Project::factory()->for($customer)->archived()->create();
        $trashed = Project::factory()->for($customer)->trashed()->create();
        $epic = Epic::factory()->for($project)->create();
        EpicComment::factory()->for($epic)->for($user)->create();
        $query = new ProjectListQuery;
        $transformer = new ProjectListTransformer;
        $request = ProjectListRequest::create('/projects', 'GET');

        $view = (new ProjectController)->index($request, $query, $transformer);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('projects13::projects.list', $view->name());
        $this->assertTrue($view->getData()['hasCustomers']);
        $this->assertNull($view->getData()['selectedCustomer']);
        $rows = array_column($view->getData()['list']['rows'], null, 'id');
        $this->assertSame(1, $rows[$project->id]['epicsCount']);
        $this->assertSame(1, $rows[$project->id]['commentsCount']);
        $this->assertSame('North customer', $rows[$project->id]['customer']);
        $this->assertSame(route('epics.index', ['search' => $project->name]), $rows[$project->id]['epicsUrl']);
        $this->assertSame('project-archive-'.$project->id, $rows[$project->id]['actions'][1]['test']);
        $this->assertSame('project-delete-'.$empty->id, $rows[$empty->id]['actions'][1]['test']);
        $this->assertSame('', $rows[$empty->id]['endDate']);
        $this->assertSame([2, 1, 1], array_column($view->getData()['list']['tabs'], 'count'));

        $archiveView = (new ProjectArchivedController)->index($request, $query, $transformer);
        $this->assertInstanceOf(View::class, $archiveView);
        $this->assertSame([$archived->id], array_column($archiveView->getData()['list']['rows'], 'id'));
        $this->assertSame('project-activate-'.$archived->id, $archiveView->getData()['list']['rows'][0]['actions'][0]['test']);
        $trashView = (new ProjectTrashController)->index($request, $query, $transformer);
        $this->assertInstanceOf(View::class, $trashView);
        $this->assertSame([$trashed->id], array_column($trashView->getData()['list']['rows'], 'id'));
        $this->assertSame('project-restore-'.$trashed->id, $trashView->getData()['list']['rows'][0]['actions'][0]['test']);
    }

    public function test_project_search_uses_customer_and_recovers_old_selection(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create(['name' => 'North %_! customer']);
        $project = Project::factory()->for($customer)->create();
        $otherCustomer = Customer::factory()->create(['name' => 'South customer']);
        Project::factory()->for($otherCustomer)->create();
        $session = app(Store::class);
        $session->flashInput(['customer_id' => $customer->id]);
        request()->setLaravelSession($session);
        $request = ProjectListRequest::create('/projects', 'GET', ['search' => '%_!']);

        $view = (new ProjectController)->index($request, new ProjectListQuery, new ProjectListTransformer);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame($customer->id, $view->getData()['selectedCustomer']->id);
        $this->assertSame([$project->id], array_column($view->getData()['list']['rows'], 'id'));
        $this->assertSame([2, 0, 0], array_column($view->getData()['list']['tabs'], 'count'));
        $this->assertSame(__('No projects match your search.'), $view->getData()['list']['emptyMessage']);
    }

    public function test_epic_lists_partition_states_and_block_deletion_with_comments(): void
    {
        $user = $this->signIn();
        $project = Project::factory()->create();
        $epic = Epic::factory()->for($project)->create(['name' => 'Dated delivery']);
        $empty = Epic::factory()->for($project)->withoutDates()->create(['name' => 'Undated delivery']);
        $archived = Epic::factory()->for($project)->archived()->create();
        $trashed = Epic::factory()->for($project)->trashed()->create();
        EpicComment::factory()->for($epic)->for($user)->create();
        $query = new EpicListQuery;
        $transformer = new EpicListTransformer;
        $request = EpicListRequest::create('/epics', 'GET');
        $request->setLaravelSession(app(Store::class));

        $view = (new EpicController)->index($request, $query, $transformer);

        $this->assertInstanceOf(View::class, $view);
        $this->assertNull($view->getData()['drawerEpic']);
        $this->assertNull($view->getData()['selectedProjectOption']);
        $this->assertTrue($view->getData()['hasProjects']);
        $rows = $view->getData()['list']['rows'];
        $this->assertSame([$epic->id, $empty->id], array_column($rows, 'id'));
        $this->assertSame(1, $rows[0]['commentsCount']);
        $this->assertSame($project->fullName(), $rows[0]['editPayload']['project_label']);
        $this->assertSame('epic-archive-'.$epic->id, $rows[0]['actions'][1]['test']);
        $this->assertSame('epic-delete-'.$empty->id, $rows[1]['actions'][1]['test']);
        $this->assertSame('', $rows[1]['startDate']);
        $this->assertSame('', $rows[1]['endDate']);
        $this->assertSame([2, 1, 1], array_column($view->getData()['list']['tabs'], 'count'));

        $archiveView = (new EpicArchivedController)->index($request, $query, $transformer);
        $this->assertInstanceOf(View::class, $archiveView);
        $this->assertSame([$archived->id], array_column($archiveView->getData()['list']['rows'], 'id'));
        $this->assertSame('epic-activate-'.$archived->id, $archiveView->getData()['list']['rows'][0]['actions'][0]['test']);
        $trashView = (new EpicTrashController)->index($request, $query, $transformer);
        $this->assertInstanceOf(View::class, $trashView);
        $this->assertSame([$trashed->id], array_column($trashView->getData()['list']['rows'], 'id'));
        $this->assertSame('epic-force-delete-'.$trashed->id, $trashView->getData()['list']['rows'][0]['actions'][1]['test']);
    }

    public function test_epic_drawer_is_resolved_outside_search_results_and_old_project_is_named(): void
    {
        $this->signIn();
        $epic = Epic::factory()->create();
        $session = app(Store::class);
        $session->put('commented_epic_id', $epic->id);
        $session->flashInput(['project_id' => $epic->project_id]);
        request()->setLaravelSession($session);
        $request = EpicListRequest::create('/epics', 'GET', ['search' => 'No matching epic']);
        $request->setLaravelSession($session);

        $view = (new EpicController)->index($request, new EpicListQuery, new EpicListTransformer);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame([], $view->getData()['list']['rows']);
        $this->assertSame($epic->id, $view->getData()['drawerEpic']['id']);
        $this->assertSame(['id' => $epic->project_id, 'text' => $epic->project->fullName()], $view->getData()['selectedProjectOption']);

        $session->put('commented_epic_id', 999999);
        $view = (new EpicController)->index($request, new EpicListQuery, new EpicListTransformer);
        $this->assertInstanceOf(View::class, $view);
        $this->assertNull($view->getData()['drawerEpic']);
    }

    public function test_trashed_epic_with_comments_has_blocked_force_delete_action(): void
    {
        $user = $this->signIn();
        $epic = Epic::factory()->create();
        EpicComment::factory()->for($epic)->for($user)->create();
        Epic::query()->whereKey($epic->id)->update(['deleted_at' => now()]);

        $view = (new EpicTrashController)->index(
            EpicListRequest::create('/epics/trash', 'GET'),
            new EpicListQuery,
            new EpicListTransformer,
        );

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('epic-force-delete-blocked-'.$epic->id, $view->getData()['list']['rows'][0]['actions'][1]['test']);
    }

    public function test_failed_edit_reopens_epic_drawer_from_old_input(): void
    {
        $this->signIn();
        $epic = Epic::factory()->create();
        $session = app(Store::class);
        $session->flashInput(['_epic_id' => $epic->id]);
        $request = EpicListRequest::create('/epics', 'GET');
        $request->setLaravelSession($session);

        $view = (new EpicController)->index($request, new EpicListQuery, new EpicListTransformer);

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame($epic->id, $view->getData()['drawerEpic']['id']);
        $this->assertSame(route('epics.comments.store', $epic), $view->getData()['drawerEpic']['commentAction']);
    }

    public function test_project_options_are_sorted_and_limited(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create(['name' => 'North customer']);
        Project::factory()->for($customer)->count(26)->sequence(
            fn ($sequence): array => ['name' => 'Project '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)],
        )->create();

        $this->getJson(route('projects.options'))
            ->assertOk()
            ->assertJsonCount(25, 'results')
            ->assertJsonPath('results.0.text', 'Project 00 (North customer)')
            ->assertJsonPath('results.24.text', 'Project 24 (North customer)');
    }

    public function test_project_options_search_parent_and_escape_wildcards(): void
    {
        $this->signIn();
        $customer = Customer::factory()->create(['name' => 'North %_! customer']);
        $project = Project::factory()->for($customer)->create(['name' => 'Website renewal']);
        Project::factory()->for($customer)->archived()->create();
        Project::factory()->for($customer)->trashed()->create();
        $otherCustomer = Customer::factory()->create(['name' => 'South customer']);
        Project::factory()->for($otherCustomer)->create();

        $this->getJson(route('projects.options', ['q' => '%_!']))
            ->assertOk()->assertExactJson(['results' => [
                ['id' => $project->id, 'text' => 'Website renewal (North %_! customer)'],
            ]]);
        $this->getJson(route('projects.options'))->assertOk()->assertJsonCount(2, 'results');
        $this->getJson(route('projects.options', ['q' => ['invalid']]))->assertUnprocessable();
    }
}
