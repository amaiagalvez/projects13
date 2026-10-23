<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Project;
use Illuminate\Validation\Rule;
use Projects13\Enums\ProjectRole;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Projects13\Models\ProjectMember;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Foundation\Http\FormRequest;

class ProjectMemberRequest extends FormRequest
{
    use ResolvesUserModel;

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
     * Adding needs an active account outside the trash that is not a member yet, and never the
     * super-admin, which nobody else may see. Changing a role keeps the member it was: the user can
     * be sent back but not swapped for another one.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $member = $this->route('member');

        if ($member instanceof ProjectMember) {
            return [
                'user_id' => ['sometimes', 'integer', Rule::in([$member->user_id])],
                'role' => ['required', 'string', Rule::enum(ProjectRole::class)],
            ];
        }

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists($this->userModelClass(), 'id')
                    ->whereNull('deleted_at')
                    ->where('active', true)
                    ->where('is_super_admin', false),
                Rule::unique(ProjectMember::class, 'user_id')
                    ->where('project_id', $project instanceof Project ? $project->getKey() : 0),
            ],
            'role' => ['required', 'string', Rule::enum(ProjectRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => __('This user is already a member of the project.'),
        ];
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
