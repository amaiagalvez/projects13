<?php

namespace Projects13\Contracts;

use Projects13\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * Display seam: labels, trail, row extras, and empty-state banner.
 */
interface ProjectDisplayIntegration
{
    /**
     * How a project is named wherever it is picked: the epic form selector, the option the epic
     * list rehydrates after a validation error, and the epic edit payload.
     */
    public function label(Project $project): string;

    /**
     * What sits under a project row on the planning and the timeline board: the breadcrumb that
     * says who the row belongs to. Empty when nothing does, which is why a board row carries an
     * empty trail rather than the project's own name.
     *
     * @return list<string>
     */
    public function trail(Project $project): array;

    /**
     * Extra fields for a list row, merged over the base ones. The same fields are merged into the
     * edit drawer payload, so a row and a drawer reopened from it always carry the same keys.
     *
     * @param  Model  $record  A project or an epic.
     * @return array<string, mixed>
     */
    public function row(Model $record): array;

    /**
     * Whether the integration has active related records that can be selected for a new project.
     * When false, the UI should offer to create one instead of showing an empty selector.
     */
    public function hasActiveRelated(): bool;

    /**
     * Banner to show when there are no active related records and the user can create a new project.
     * Returns null if no banner should be shown.
     *
     * @return array{message: string, action_label: string, action_url: string}|null
     */
    public function noRelatedBanner(): ?array;
}
