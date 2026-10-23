<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Basics13\Support\Validation\MaxLength;
use Illuminate\Foundation\Http\FormRequest;

class EpicRequest extends FormRequest
{
    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['project_id' => __('Project')];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /**
     * An epic of a project the user is not a member of answers "not found", which a plain boolean
     * would turn into a 403, so the policy's response is handed back as it is.
     */
    public function authorize(): bool|Response
    {
        $epic = $this->route('epic');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $response = Gate::forUser($user)->inspect($epic ? 'update' : 'create', $epic ?: Epic::class);

        return $response->allowed() ?: $response;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'min:4',
                'max:'.MaxLength::string(),
                $this->uniqueNamePerProject(),
            ],
            'start_date' => ['nullable', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after:start_date'],
            'project_id' => [
                'required',
                'integer',
                $this->selectableProjectRule(),
            ],
            'notes' => ['nullable', 'string', 'max:'.MaxLength::longText()],
            'reuse_deleted_name' => ['sometimes', 'boolean'],
        ];

        // Add project date boundary validation after project_id is validated
        $projectId = $this->integer('project_id');
        if ($projectId > 0) {
            $project = Project::withTrashed()->find($projectId);
            if ($project) {
                $rules['start_date'][] = 'after_or_equal:'.$project->start_date->format('Y-m-d');
                if ($project->end_date) {
                    // Both dates, since an epic may have a start date and no end date.
                    $rules['start_date'][] = 'before_or_equal:'.$project->end_date->format('Y-m-d');
                    $rules['end_date'][] = 'before_or_equal:'.$project->end_date->format('Y-m-d');
                }
            }
        }

        return $rules;
    }

    /**
     * Epic names are unique inside their project, not globally. Scoping the rule needs a usable
     * project_id: integer() returns 0 for an absent or non-numeric value, and a uniqueness check
     * against project 0 reports a duplicate name for a field the project_id rule has already
     * rejected. So the scope is only applied when the id is there; when it is not, the rule falls
     * back to global uniqueness, which never lets a duplicate name through.
     */
    protected function uniqueNamePerProject(): Unique
    {
        $rule = Rule::unique(Epic::class)
            ->ignore($this->route('epic'))
            ->whereNull('deleted_at');

        $projectId = $this->integer('project_id');

        return $projectId > 0 ? $rule->where('project_id', $projectId) : $rule;
    }

    /**
     * An epic may only hang from an active project, which is what the selector offers. While
     * editing, the project the epic already belongs to stays valid even after it has been
     * deactivated, so a form that does not change it can still be saved.
     */
    protected function selectableProjectRule(): Exists
    {
        $current = $this->route('epic');

        if ($current instanceof Epic && $current->project_id === $this->integer('project_id')) {
            return Rule::exists(Project::class, 'id')->whereNull('deleted_at');
        }

        return Rule::exists(Project::class, 'id')
            ->whereNull('deleted_at')
            ->where('active', true);
    }
}
