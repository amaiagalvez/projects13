<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Project;
use Illuminate\Validation\Rule;
use Projects13\Enums\ProjectRole;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

class ProjectMemberRequest extends FormRequest
{
    /**
     * Keep member errors apart from the project form errors shown in the same drawer.
     *
     * @var string
     */
    protected $errorBag = 'member';

    public function authorize(): bool|Response
    {
        $project = $this->route('project');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $response = Gate::forUser($user)->inspect('manageMembers', $project ?: Project::class);

        return $response->allowed() ?: $response;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $member = $this->route('member');

        /** @var Project|null $project */
        $project = $project instanceof Project ? $project : null;

        $rules = [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                // On store, ensure user is not already a member
                function (string $attribute, mixed $value, \Closure $fail) use ($project, $member) {
                    if ($project && ! $member) {
                        if ($project->members()->where('user_id', $value)->exists()) {
                            $fail(__('This user is already a member of the project.'));
                        }
                    }
                },
            ],
            'role' => ['required', 'string', Rule::enum(ProjectRole::class)],
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('User'),
            'role' => __('Role'),
        ];
    }
}
