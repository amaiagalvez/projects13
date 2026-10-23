<?php

namespace Projects13\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Removes a member. The route carries no body, so only the right to manage the members is checked.
 */
class ProjectMemberDestroyRequest extends FormRequest
{
    public function authorize(): bool|Response
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $response = Gate::forUser($user)->inspect('manageMembers', $this->route('project'));

        return $response->allowed() ?: $response;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
