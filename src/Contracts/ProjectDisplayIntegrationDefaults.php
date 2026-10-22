<?php

namespace Projects13\Contracts;

use Projects13\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * Default implementations for ProjectDisplayIntegration.
 * Integration packages can use this trait to get empty default implementations.
 */
trait ProjectDisplayIntegrationDefaults
{
    public function label(Project $project): string
    {
        return $project->name;
    }

    /** @return list<string> */
    public function trail(Project $project): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function row(Model $record): array
    {
        return [];
    }

    public function hasActiveRelated(): bool
    {
        return false;
    }

    /**
     * @return array{message: string, action_label: string, action_url: string}|null
     */
    public function noRelatedBanner(): ?array
    {
        return null;
    }
}
