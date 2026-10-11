@php
    $editingEpic = $list['create'] && str_starts_with(old('_epic_form', ''), 'edit-');
    $deletedEpicConflict = session('deleted_epic_conflict');
    $hasCommentErrors = $errors->getBag('comment')->any();
    // The drawer payload is resolved by id in EpicController, so it also works when the epic is not
    // on the current page.
    $drawerEpic = $list['create'] ? ($drawerEpic ?? null) : null;
    $commentedEpic = $drawerEpic;
    $editingEpicPayload = $editingEpic ? $drawerEpic : null;
    $initialForm = $list['create']
        ? [
            'id' => $editingEpic ? old('_epic_id') : null,
            'name' => old('name', ''),
            'notes' => old('notes', ''),
            'start_date' => old('start_date', ''),
            'end_date' => old('end_date', ''),
            'project_id' => old('project_id', ''),
            'comments' => [],
            'commentsCount' => $editingEpicPayload['commentsCount'] ?? 0,
            'commentsLoading' => false,
            'commentsError' => '',
            'commentAction' => $editingEpicPayload['commentAction'] ?? '',
            'commentBody' => '',
            'commentNotes' => '',
            'context' => $editingEpic ? old('_epic_form') : 'create',
            'method' => $editingEpic ? 'PUT' : 'POST',
            'action' => $editingEpic
                ? route('epics.update', old('_epic_id'))
                : route('epics.store'),
            'title' => $editingEpic ? __('Edit epic') : __('New epic'),
            'submitLabel' => $editingEpic ? __('Update epic') : __('Save epic'),
        ]
        : null;
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="listConfirmation({
        form: @js($initialForm),
        projectOptions: @js(($selectedProjectOption ?? null) ? [$selectedProjectOption] : []),
        projectOptionsUrl: @js(route('projects.options', [], false)),
        projectOptionsRequest: null,
        projectOptionsLoading: false,
        projectOptionsSearched: false,
        projectOptionsResultCount: 0,
        projectOptionsError: '',
        storeUrl: @js(route('epics.store')),
        updateUrl: @js(route('epics.update', '__EPIC__')),
        async searchProjects() {
            this.projectOptionsRequest?.abort();
            const controller = new AbortController();
            this.projectOptionsRequest = controller;
            this.projectOptionsLoading = true;
            this.projectOptionsError = '';
    
            const url = new URL(this.projectOptionsUrl, window.location.href);
            url.searchParams.set('q', '');
    
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
    
                if (!response.ok) {
                    throw new Error();
                }
    
                const result = await response.json();
                const selectedProject = this.projectOptions.find(
                    (project) => String(project.id) === String(this.form.project_id),
                );
                const results = result.results;
                this.projectOptionsResultCount = results.length;
                this.projectOptions = selectedProject && !results.some(
                    (project) => String(project.id) === String(selectedProject.id),
                ) ? [selectedProject, ...results] : results;
                this.projectOptionsSearched = true;
            } catch {
                if (!controller.signal.aborted) {
                    this.projectOptionsError = @js(__('Unable to load projects.'));
                }
            } finally {
                if (this.projectOptionsRequest === controller) {
                    this.projectOptionsLoading = false;
                }
            }
        },
        createEpic() {
            this.projectOptionsRequest?.abort();
            this.projectOptions = [];
            this.projectOptionsSearched = false;
            this.projectOptionsResultCount = 0;
            this.projectOptionsError = '';
            this.form = {
                id: null,
                name: '',
                notes: '',
                start_date: '',
                end_date: '',
                project_id: '',
                comments: [],
                commentsCount: 0,
                commentsLoading: false,
                commentsError: '',
                commentAction: '',
                commentBody: '',
                commentNotes: '',
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New epic')),
                submitLabel: @js(__('Save epic')),
            };
        },
        editEpic(epic, commentBody = '', commentNotes = '') {
            this.commentRequest?.abort();
            this.projectOptionsRequest?.abort();
            this.projectOptions = [{
                id: String(epic.project_id),
                text: epic.project_label,
            }];
            this.projectOptionsSearched = false;
            this.projectOptionsResultCount = 0;
            this.projectOptionsError = '';
            this.form = {
                ...epic,
                comments: [],
                commentsLoading: false,
                commentsError: '',
                project_id: String(epic.project_id),
                commentBody,
                commentNotes,
                context: `edit-${epic.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__EPIC__', epic.id),
                title: @js(__('Edit epic')),
                submitLabel: @js(__('Update epic')),
            };
            this.loadEpicComments();
        },
        async loadEpicComments() {
            this.commentRequest?.abort();
            const controller = new AbortController();
            const epicId = this.form.id;
            this.commentRequest = controller;
            this.form.comments = [];
            this.form.commentsLoading = true;
            this.form.commentsError = '';
    
            try {
                const response = await fetch(this.form.commentsUrl, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
    
                if (!response.ok) {
                    throw new Error();
                }
    
                const result = await response.json();
    
                if (this.form.id === epicId) {
                    this.form.comments = result.comments;
                }
            } catch {
                if (!controller.signal.aborted && this.form.id === epicId) {
                    this.form.commentsError = @js(__('Unable to load comments.'));
                }
            } finally {
                if (this.form.id === epicId) {
                    this.form.commentsLoading = false;
                }
    
                if (this.commentRequest === controller) {
                    this.commentRequest = null;
                }
            }
        },
    })" @if ($commentedEpic)
        x-init="editEpic(@js($commentedEpic), @js($hasCommentErrors ? old('body', '') : ''), @js($hasCommentErrors ? old('notes', '') : ''));
        $nextTick(() => $dispatch('modal-show', { name: 'epic-form' }))"
    @elseif ($errors->any())
        x-init="@if($editingEpicPayload)
        loadEpicComments();
        @endif $nextTick(() => $dispatch('modal-show', { name: 'epic-form' }))"
    @elseif ($deletedEpicConflict)
        x-init="$nextTick(() => $dispatch('modal-show', { name: 'epic-name-conflict' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="epic">
            <x-slot:actions>
                @if ($list['create'])
                    <x-basics13::list.create-action prefix="epic" :label="__('New epic')" click="createEpic()" />
                @endif
            </x-slot:actions>
        </x-basics13::list.page-header>

        @if ($list['create'] && ! ($hasProjects ?? false))
            <flux:callout icon="exclamation-triangle" variant="warning">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('No active projects are available. Open the project form to create one.') }}</span>
                    <flux:button size="sm" variant="primary"
                        :href="route('projects.index', ['create' => 1])"
                        data-test="epic-no-project-form-button">
                        {{ __('Open project form') }}
                    </flux:button>
                </div>
            </flux:callout>
        @endif

        <x-basics13::list.flash prefix="epic" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$epics->total()">
                <x-basics13::list.search :search="$list['search']" prefix="epic" />

                <x-basics13::list.table prefix="epic" :paginator="$epics">
                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Project') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Customer') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Start date') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('End date') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Comments') }}</flux:table.column>
                        <flux:table.column scope="col" align="end" sticky class="text-end">
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($list['rows'] as $row)
                            <flux:table.row :key="$row['id']"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:table.cell class="max-w-[16rem]">
                                    <flux:modal.trigger name="epic-form">
                                        <button type="button"
                                            class="block max-w-full truncate text-start font-medium text-zinc-900 hover:underline dark:text-zinc-100"
                                            x-on:click="editEpic(JSON.parse($el.dataset.payload))"
                                            data-payload="{{ json_encode($row['editPayload']) }}"
                                            title="{{ $row['name'] }}"
                                            data-test="epic-name-{{ $row['id'] }}">{{ $row['name'] }}</button>
                                    </flux:modal.trigger>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[16rem] truncate"
                                    :title="$row['project']">
                                    {{ $row['project'] }}
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[16rem] truncate"
                                    :title="$row['customer']">
                                    {{ $row['customer'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-basics13::list.local-time :datetime="$row['startDate']" format="date" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-basics13::list.local-time :datetime="$row['endDate']" format="date" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-basics13::list.count icon="chat-bubble-left" :count="$row['commentsCount']"
                                        :label="__('Comments: :count', ['count' => $row['commentsCount']])"
                                        data-test="epic-comments-count-{{ $row['id'] }}" />
                                </flux:table.cell>
                                <flux:table.cell align="end" sticky
                                    class="bg-white dark:bg-zinc-900">
                                    <x-basics13::list.row-actions :actions="$row['actions']" prefix="epic"
                                        edit-handler="editEpic" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :colspan="7" :message="$list['emptyMessage']" />
                        @endforelse
                    </flux:table.rows>
                </x-basics13::list.table>
            </x-basics13::list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="epic-form" aria-labelledby="epic-form-heading" variant="flyout"
                position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('projects13::epics.form')
            </flux:modal>

            @if ($deletedEpicConflict)
                <x-basics13::name-conflict-modal name="epic-name-conflict" :title="__('Record Name already in trash')"
                    :message="__('A deleted record already uses the name :name.', [
                        'name' => $deletedEpicConflict['name'],
                    ])" :create-action="route('epics.store')" :restore-action="route('epics.trash.restore', $deletedEpicConflict['id'])" :create-fields="[
                        'name' => old('name', $deletedEpicConflict['name']),
                        'start_date' => old('start_date'),
                        'end_date' => old('end_date'),
                        'project_id' => old('project_id'),
                    ]"
                    :create-label="__('Create a new epic')" :restore-label="__('Restore the deleted record instead')" create-test="epic-conflict-create-new"
                    restore-test="epic-conflict-restore" />
            @endif
        @endif

        <x-basics13::list.confirm-modal prefix="epic" />
    </div>
</x-layouts::app>
