<x-basics13::forms.tracked-resource prefix="project" context-field="_project_form" id-field="_project_id"
    submit-disabled="isSubmitting || !isDirty || form.customerCreating">
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="name"
            test="project-name-info" for="project-name">
            {{ __('Name') }}
        </x-forms.field-label>
        <flux:input name="name" x-model="form.name" minlength="4"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::string() }}" required
            autofocus id="project-name" data-test="project-name" />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="start_date"
            test="project-start-date-info" for="project-start-date">
            {{ __('Start date') }}
        </x-forms.field-label>
        <flux:input type="date" name="start_date" x-model="form.start_date" required
            id="project-start-date" data-test="project-start-date" />
        <flux:error name="start_date" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="end_date"
            test="project-end-date-info" for="project-end-date" :labels="['start_date' => __('Start date')]">
            {{ __('End date') }}
        </x-forms.field-label>
        <flux:input type="date" name="end_date" x-model="form.end_date"
            x-bind:min="form.start_date" id="project-end-date" data-test="project-end-date" />
        <flux:error name="end_date" />
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="customer_id"
            test="project-customer-info" for="project-customer-id"
            :notices="[__('Choose a customer from the list or create a new one.')]">
            {{ __('Customer') }}
        </x-forms.field-label>
        <select id="project-customer-id" name="customer_id" x-model="form.customer_id"
            x-effect="
                const customerId = String(form.customer_id || '');
                if (customerId && form.customer_name) {
                    let option = Array.from($el.options).find((item) => item.value === customerId);
                    if (!option) {
                        option = new Option(form.customer_name, customerId, true, true);
                        $el.add(option);
                    }
                    option.selected = true;
                }
                window.jQuery($el).val(customerId || null).trigger('change.select2')
            "
            required data-test="project-customer" data-project-customer-select
            data-customer-store-url="{{ route('customers.store', [], false) }}"
            data-customer-search-url="{{ route('customers.options', [], false) }}"
            data-create-label="{{ __('Create customer') }}"
            data-create-error="{{ __('Unable to create customer.') }}"
            data-search-error="{{ __('Unable to load customers.') }}"
            data-no-results-label="{{ __('No customers found.') }}"
            data-placeholder="{{ __('Select a customer') }}">
            <option></option>
            @if ($selectedCustomer)
                <option value="{{ $selectedCustomer->id }}">{{ $selectedCustomer->name }}</option>
            @endif
        </select>
        <flux:error name="customer_id" />
        <p role="alert" x-show="form.customerCreateError" x-text="form.customerCreateError"
            class="text-sm text-red-600" data-test="project-customer-error"></p>
    </flux:field>
    <flux:field>
        <x-forms.field-label :request="\Projects13\Http\Requests\ProjectRequest::class" field="notes"
            test="project-notes-info" for="project-notes">
            {{ __('Notes') }}
        </x-forms.field-label>
        <flux:textarea name="notes" x-model="form.notes" rows="3"
            maxlength="{{ \Basics13\Support\Validation\MaxLength::longText() }}"
            id="project-notes" data-test="project-notes" />
        <flux:error name="notes" />
    </flux:field>
</x-basics13::forms.tracked-resource>
