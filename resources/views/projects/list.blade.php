@php
    $editingProject = $list['create'] && str_starts_with(old('_project_form', ''), 'edit-');
    $deletedProjectConflict = session('deleted_project_conflict');

    // The fields an integration owns, carried over from the previous submission so a form that
    // failed validation comes back with what the user had already chosen. The base package does
    // not know what those fields are: the integration declares them next to the view it fills in.
    $integrationState = [];

    foreach (config('projects13.views.project_form.state', []) as $integrationField) {
        $integrationState[$integrationField] = old($integrationField, '');
    }

    // The same keys, blank, for a drawer opened fresh from the "new" button rather than from a
    // failed submission.
    $integrationFreshState = array_fill_keys(array_keys($integrationState), '');

    $initialForm = $list['create']
        ? [
            'id' => $editingProject ? old('_project_id') : null,
            'name' => old('name', ''),
            'notes' => old('notes', ''),
            'start_date' => old('start_date', ''),
            'end_date' => old('end_date', ''),
            'context' => $editingProject ? old('_project_form') : 'create',
            'method' => $editingProject ? 'PUT' : 'POST',
            'action' => $editingProject
                ? route('projects.update', old('_project_id'))
                : route('projects.store'),
            'title' => $editingProject ? __('Edit project') : __('New project'),
            'submitLabel' => $editingProject ? __('Update project') : __('Save project'),
            ...$integrationState,
        ]
        : null;
@endphp

<x-layouts::app :title="$list['resource']">
    <div data-project-form-root x-data="listConfirmation({
        form: @js($initialForm),
        storeUrl: @js(route('projects.store')),
        updateUrl: @js(route('projects.update', '__PROJECT__')),
        createProject() {
            this.form = {
                id: null,
                name: '',
                notes: '',
                start_date: '',
                end_date: '',
                ...@js($integrationFreshState),
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New project')),
                submitLabel: @js(__('Save project')),
            };
        },
        editProject(project) {
            this.form = {
                ...project,
                context: `edit-${project.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__PROJECT__', project.id),
                title: @js(__('Edit project')),
                submitLabel: @js(__('Update project')),
            };
        },
    })"
        @if ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-form' }))"
        @elseif ($deletedProjectConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-name-conflict' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createProject(); $nextTick(() => $dispatch('modal-show', { name: 'project-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-basics13::list.page-header :list="$list" prefix="project">
            <x-slot:actions>
                @if ($list['create'])
                    <x-basics13::list.create-action prefix="project" :label="__('New project')"
                        click="createProject()" />
                @endif
            </x-slot:actions>
        </x-basics13::list.page-header>

        <x-basics13::list.flash prefix="project" />

        @fragment('list-results')
            <x-basics13::list.searchable-results :search="$list['search']" :total="$projects->total()">
                <x-basics13::list.search :search="$list['search']" prefix="project" />

                <x-basics13::list.table prefix="project" :paginator="$projects">
                    <flux:table.columns>
                        <flux:table.column scope="col">{{ __('Name') }}</flux:table.column>
                        @includeIf(config('projects13.views.project_list.columns'))
                        <flux:table.column scope="col">{{ __('Start date') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('End date') }}</flux:table.column>
                        <flux:table.column scope="col">{{ __('Epics') }}</flux:table.column>
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
                                    <flux:modal.trigger name="project-form">
                                        <button type="button"
                                            class="block max-w-full truncate text-start font-medium text-zinc-900 hover:underline dark:text-zinc-100"
                                            x-on:click="editProject(JSON.parse($el.dataset.payload))"
                                            data-payload="{{ json_encode($row['editPayload']) }}"
                                            title="{{ $row['name'] }}"
                                            data-test="project-name-{{ $row['id'] }}">{{ $row['name'] }}</button>
                                    </flux:modal.trigger>
                                </flux:table.cell>
                                @includeIf(config('projects13.views.project_list.cells'))
                                <flux:table.cell>
                                    <x-basics13::list.local-time :datetime="$row['startDate']" format="date" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-basics13::list.local-time :datetime="$row['endDate']" format="date" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-basics13::list.count icon="flag" :count="$row['epicsCount']"
                                        :url="$row['epicsUrl']"
                                        :label="__('View :count epics', ['count' => $row['epicsCount']])"
                                        data-test="project-epics-count-{{ $row['id'] }}" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-basics13::list.count icon="chat-bubble-left" :count="$row['commentsCount']"
                                        :label="__('Comments: :count', ['count' => $row['commentsCount']])"
                                        data-test="project-comments-count-{{ $row['id'] }}" />
                                </flux:table.cell>
                                <flux:table.cell align="end" sticky
                                    class="bg-white dark:bg-zinc-900">
                                    <x-basics13::list.row-actions :actions="$row['actions']" prefix="project"
                                        edit-handler="editProject" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-basics13::list.empty-state :colspan="6 + (int) config('projects13.views.project_list.count', 0)" :message="$list['emptyMessage']" />
                        @endforelse
                    </flux:table.rows>
                </x-basics13::list.table>
            </x-basics13::list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="project-form" aria-labelledby="project-form-heading" variant="flyout"
                position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('projects13::projects.form')
            </flux:modal>

            @if ($deletedProjectConflict)
                <x-basics13::name-conflict-modal name="project-name-conflict" :title="__('Record Name already in trash')"
                    :message="__('A deleted record already uses the name :name.', [
                        'name' => $deletedProjectConflict['name'],
                    ])" :create-action="route('projects.store')" :restore-action="route('projects.trash.restore', $deletedProjectConflict['id'])" :create-fields="[
                        'name' => old('name', $deletedProjectConflict['name']),
                        'start_date' => old('start_date'),
                        'end_date' => old('end_date'),
                    ]"
                    :create-label="__('Create a new project')" :restore-label="__('Restore the deleted record instead')" create-test="project-conflict-create-new"
                    restore-test="project-conflict-restore" />
            @endif
        @endif

        <x-basics13::list.confirm-modal prefix="project" />
    </div>
</x-layouts::app>
