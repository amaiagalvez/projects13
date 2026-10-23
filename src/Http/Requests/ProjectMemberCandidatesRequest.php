<?php

namespace Projects13\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Basics13\Http\Requests\SearchableSelectOptionsRequest;

/**
 * Searching the accounts to add follows the right to manage the members, not `users.view`: an
 * owner has to find the people they share the project with.
 */
class ProjectMemberCandidatesRequest extends SearchableSelectOptionsRequest
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
}
