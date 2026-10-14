<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Project;
use Illuminate\Validation\Rule;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Projects13\Contracts\ProjectValidationIntegration;

class ProjectRequest extends FormRequest
{
    /**
     * Resolved from the container rather than injected: Laravel builds a form request itself and
     * passes the incoming payload to the parent constructor, so this one takes no arguments of its
     * own.
     */
    private function integration(): ProjectValidationIntegration
    {
        return app(ProjectValidationIntegration::class);
    }

    /**
     * The base knows nothing about the fields an integration adds, so it asks it how to name them.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'start_date' => __('Start date'),
            'end_date' => __('End date'),
            'notes' => __('Notes'),
            ...$this->integration()->attributes(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project
            ? ($this->user()?->can('update', $project) ?? false)
            : ($this->user()?->can('create', Project::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:4',
                'max:'.MaxLength::string(),
                Rule::unique(Project::class)
                    ->ignore($this->route('project'))
                    ->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
            ...$this->integration()->rules($this),
        ];
    }
}
