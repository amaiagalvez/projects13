<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Basics13\Http\Requests\RestoreRequest;

/**
 * @extends RestoreRequest<Project>
 */
final class ProjectRestoreRequest extends RestoreRequest
{
    /**
     * @return Project
     */
    protected function findTrashed(): Model
    {
        return Project::onlyTrashed()
            ->findOrFail((int) $this->route('project'));
    }
}
