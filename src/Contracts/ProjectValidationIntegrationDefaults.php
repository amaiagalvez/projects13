<?php

namespace Projects13\Contracts;

use Projects13\Models\Project;
use Projects13\Http\Requests\ProjectRequest;

/**
 * Default implementations for ProjectValidationIntegration.
 * Integration packages can use this trait to get empty default implementations.
 */
trait ProjectValidationIntegrationDefaults
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ProjectRequest $request): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function saving(ProjectRequest $request, ?Project $project): array
    {
        return [];
    }
}
