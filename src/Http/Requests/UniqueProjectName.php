<?php

namespace Projects13\Http\Requests;

use Projects13\Models\Project;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueProjectName implements DataAwareRule, Rule
{
    private string $message = 'The name has already been taken.';

    public function __construct(
        private ProjectRequest $request
    ) {}

    /**
     * @param  array<int|string, mixed>  $data
     */
    public function setData(array $data): static
    {
        return $this;
    }

    public function passes(mixed $attribute, mixed $value): bool
    {
        // Check if a project with this name already exists (including soft-deleted)
        $existing = Project::withTrashed()->where('name', $value)->first();

        if (! $existing) {
            return true;
        }

        // If it's the same project being updated, allow it
        $current = $this->request->route('project');
        if ($current instanceof Project && $existing->is($current)) {
            return true;
        }

        // If the existing project is soft-deleted and we're explicitly reusing the name, allow it
        if ($existing->trashed() && $this->request->boolean('reuse_deleted_name')) {
            return true;
        }

        return false;
    }

    public function message(): string|PotentiallyTranslatedString
    {
        return $this->message;
    }
}
