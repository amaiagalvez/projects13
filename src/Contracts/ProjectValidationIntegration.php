<?php

namespace Projects13\Contracts;

use Projects13\Models\Project;
use Projects13\Http\Requests\ProjectRequest;

/**
 * Validation seam: rules, attribute names, and attributes to persist.
 */
interface ProjectValidationIntegration
{
    /**
     * Rules to merge over the base project rules, keyed by field name.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(ProjectRequest $request): array;

    /**
     * Validation attribute names for the fields contributed by `rules()`, so the error summary
     * reads in the user's language instead of showing a raw field name.
     *
     * @return array<string, string>
     */
    public function attributes(): array;

    /**
     * Attributes to write on the project. Runs inside the same transaction that persists it, so an
     * implementation may lock whatever it validates here. Only return attributes this integration
     * validated in `rules()`; the caller writes them with `forceFill()` because they are not part
     * of the base model.
     *
     * @return array<string, mixed>
     */
    public function saving(ProjectRequest $request, ?Project $project): array;
}
