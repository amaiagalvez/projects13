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
            x-bind:min="form.start_date" id="project-end-date" data-test="project-end-date" />
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
