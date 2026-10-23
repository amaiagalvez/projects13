@php
    // The base conditions plus whatever an integration needs the submit button to wait for, e.g.
    // while it creates a record on the user's behalf.
    $submitDisabled = collect([
        'isSubmitting',
        '!isDirty',
        config('projects13.views.project_form.submit_guard'),
    ])->filter()->implode(' || ');
@endphp

<x-basics13::forms.tracked-resource prefix="project" context-field="_project_form" id-field="_project_id"
    submit-disabled="{{ $submitDisabled }}">
    <flux:field>
        <x-basics13::forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="name"
            test="project-name-info" for="project-name">
            {{ __('Name') }}
        </x-basics13::forms.field-label>
        <flux:input name="name" x-model="form.name" minlength="4"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required
            autofocus id="project-name" data-test="project-name" />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <x-basics13::forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="start_date"
            test="project-start-date-info" for="project-start-date">
            {{ __('Start date') }}
        </x-basics13::forms.field-label>
        <flux:input type="date" name="start_date" x-model="form.start_date" required
            id="project-start-date" data-test="project-start-date" />
        <flux:error name="start_date" />
    </flux:field>
    <flux:field>
        <x-basics13::forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="end_date"
            test="project-end-date-info" for="project-end-date" :labels="['start_date' => __('Start date')]">
            {{ __('End date') }}
        </x-basics13::forms.field-label>
        <flux:input type="date" name="end_date" x-model="form.end_date"
            x-bind:min="form.start_date" id="project-end-date" data-test="project-end-date"
            aria-describedby="project-end-date-hint" />
        <x-basics13::forms.field-hint :request="\Projects13\Http\Requests\ProjectRequest::class" field="end_date"
            id="project-end-date-hint" :labels="['start_date' => __('Start date')]" />
        <flux:error name="end_date" />
    </flux:field>
    {{-- Where an integration that owns a field of this form puts it. Null by default. --}}
    @includeIf(config('projects13.views.project_form.fields'))
    <flux:field>
        <x-basics13::forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="notes"
            test="project-notes-info" for="project-notes">
            {{ __('Notes') }}
        </x-basics13::forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="project-notes" data-test="project-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-basics13::forms.tracked-resource>

{{--
    The members of the project, under its form and apart from it: every change is saved on its own,
    so it never marks the project form dirty. Only shown while editing a project whose members the
    user may manage (the row payload carries their URLs then).
--}}
<section x-show="form.members" x-cloak data-test="project-members" aria-labelledby="project-members-heading"
    x-data="{
        members: [],
        query: '',
        candidates: [],
        picked: null,
        role: 'viewer',
        confirming: null,
        error: '',
        busy: false,
        loadedFor: null,
        async request(url, method = 'GET', body = null) {
            const token = this.$el.closest('.form-drawer')?.querySelector('input[name=_token]')?.value ?? '';
            const response = await fetch(url, {
                method,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: body === null ? null : JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(data.message ?? @js(__('The change could not be saved.')));
            }

            return data;
        },
        async load() {
            const members = (await this.request(form.members.index)).members;
            // Rendered afresh: after a refused role change the data is the same as before, and only
            // a new row puts the saved role back in its select.
            this.members = [];
            await this.$nextTick();
            this.members = members;
        },
        async search() {
            const url = new URL(form.members.candidates, window.location.origin);
            url.searchParams.set('q', this.query);
            this.candidates = (await this.request(url.toString())).results;
        },
        async run(action) {
            this.error = '';
            this.busy = true;

            try {
                await action();
            } catch (exception) {
                this.error = exception.message;
            }

            // Reloaded either way, so a refused change does not stay on screen.
            await this.load().catch((exception) => this.error ||= exception.message);
            this.busy = false;
        },
        add() {
            return this.run(async () => {
                await this.request(form.members.store, 'POST', { user_id: this.picked.id, role: this.role });
                this.picked = null;
                this.query = '';
                this.candidates = [];
            });
        },
        changeRole(member, role) {
            return this.run(() => this.request(`${form.members.store}/${member.id}`, 'PATCH', { role }));
        },
        remove(member) {
            return this.run(async () => {
                await this.request(`${form.members.store}/${member.id}`, 'DELETE');
                this.confirming = null;
            });
        },
    }"
    x-effect="if (form.members && form.members.index !== loadedFor) { loadedFor = form.members.index; error = ''; confirming = null; load().catch((exception) => error = exception.message) }"
    class="mx-auto mt-8 flex w-full max-w-xl flex-col gap-4 border-t border-zinc-200 pt-6 dark:border-white/10">
    <div>
        <flux:heading id="project-members-heading" level="3" size="lg">{{ __('Members') }}</flux:heading>
        <flux:text class="text-sm">{{ __('Who can see this project. Owners also manage its members.') }}</flux:text>
    </div>

    <p role="alert" x-show="error" x-text="error" class="text-sm text-red-600 dark:text-red-400"
        data-test="project-members-error"></p>

    <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-white/5 dark:border-white/10"
        x-bind:aria-busy="busy">
        <template x-for="member in members" x-bind:key="member.id">
            <li class="flex flex-wrap items-center gap-3 px-3 py-2.5" x-bind:data-test="`project-member-${member.user_id}`">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-zinc-800 dark:text-zinc-100" x-text="member.name"></span>
                    <span class="block truncate text-xs text-zinc-600 dark:text-zinc-400" x-text="member.email"></span>
                </span>
                <label class="sr-only" x-bind:for="`project-member-role-${member.id}`">{{ __('Role') }}</label>
                <select x-bind:id="`project-member-role-${member.id}`" x-bind:value="member.role"
                    x-on:change="changeRole(member, $event.target.value)" x-bind:disabled="busy"
                    x-bind:data-test="`project-member-role-${member.user_id}`"
                    class="rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm text-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:border-white/10 dark:bg-white/10 dark:text-zinc-100">
                    @foreach (\Projects13\Enums\ProjectRole::cases() as $projectRole)
                        <option value="{{ $projectRole->value }}">{{ $projectRole->label() }}</option>
                    @endforeach
                </select>
                {{-- Removing takes a second click that names what happens, instead of a modal over the drawer. --}}
                <flux:button type="button" size="sm" variant="ghost" icon="x-mark" x-show="confirming !== member.id"
                    x-on:click="confirming = member.id" x-bind:aria-label="`{{ __('Remove') }} ${member.name}`"
                    x-bind:data-test="`project-member-remove-${member.user_id}`" />
                <flux:button type="button" size="sm" variant="danger" x-show="confirming === member.id"
                    x-on:click="remove(member)" x-bind:disabled="busy"
                    x-bind:data-test="`project-member-remove-confirm-${member.user_id}`">
                    {{ __('Remove from the project') }}
                </flux:button>
            </li>
        </template>
    </ul>

    <div class="flex flex-col gap-2">
        <label for="project-member-search" class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Add a member') }}</label>
        <flux:input id="project-member-search" x-model="query" x-on:input.debounce.300ms="search().catch((exception) => error = exception.message)"
            x-on:focus="candidates.length || search().catch(() => {})" icon="magnifying-glass"
            placeholder="{{ __('Search people by name') }}" autocomplete="off" data-test="project-member-search" />
        <ul x-show="candidates.length && ! picked" class="max-h-48 overflow-y-auto rounded-lg border border-zinc-200 dark:border-white/10"
            data-test="project-member-candidates">
            <template x-for="candidate in candidates" x-bind:key="candidate.id">
                <li>
                    <button type="button" x-on:click="picked = candidate; query = candidate.text"
                        x-bind:data-test="`project-member-candidate-${candidate.id}`"
                        class="block w-full px-3 py-2 text-start text-sm text-zinc-700 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus dark:text-zinc-300 dark:hover:bg-white/5"
                        x-text="candidate.text"></button>
                </li>
            </template>
        </ul>
        <div class="flex flex-wrap items-center gap-2">
            <label class="sr-only" for="project-member-new-role">{{ __('Role') }}</label>
            <select id="project-member-new-role" x-model="role" data-test="project-member-new-role"
                class="rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm text-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus dark:border-white/10 dark:bg-white/10 dark:text-zinc-100">
                @foreach (\Projects13\Enums\ProjectRole::cases() as $projectRole)
                    <option value="{{ $projectRole->value }}">{{ $projectRole->label() }}</option>
                @endforeach
            </select>
            <flux:button type="button" size="sm" icon="plus" x-on:click="add()" x-bind:disabled="busy || ! picked"
                data-test="project-member-add">
                {{ __('Add member') }}
            </flux:button>
        </div>
    </div>
</section>
