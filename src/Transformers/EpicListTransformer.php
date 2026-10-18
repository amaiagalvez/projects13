<?php

namespace Projects13\Transformers;

use Projects13\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Basics13\Transformers\ListTransformer;
use Projects13\Contracts\ProjectDisplayIntegration;

/**
 * @extends ListTransformer<Epic>
 */
class EpicListTransformer extends ListTransformer
{
    public function __construct(private readonly ProjectDisplayIntegration $integration) {}

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function columns(Model $record, array $extra): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'project' => $record->project->name,
            'project_active' => $record->project->active,
            'startDate' => $record->start_date?->format('Y-m-d') ?? '',
            'endDate' => $record->end_date?->format('Y-m-d') ?? '',
            'commentsCount' => (int) $record->comments_count,
            'editPayload' => $this->editPayload($record),
            ...$extra,
        ];
    }

    protected function getDeleteBlockCondition(Model $record): bool
    {
        return $record->comments_count > 0;
    }

    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return $record->comments_count > 0;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return __('Cannot be permanently deleted while it has related records.');
    }

    protected function integrationRow(Model $record): array
    {
        return $this->integration->row($record);
    }

    /**
     * The payload the edit drawer needs for one epic. The epic list uses it to rehydrate the drawer
     * after a comment or a failed edit, which may target an epic that is not on the current page,
     * so it loads the relations the payload needs instead of relying on a query that did it.
     *
     * The payload itself is `editPayload()`: a drawer reopened from here and a drawer opened from a
     * row carry the very same fields, so they are built in one place.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(Epic $epic): array
    {
        $epic->loadMissing('project');

        return $this->editPayload($epic);
    }

    /**
     * @param  Epic  $record
     * @return array<string, mixed>
     */
    protected function editPayload(Model $record): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'notes' => $record->notes,
            'start_date' => $record->start_date?->toDateString() ?? '',
            'end_date' => $record->end_date?->toDateString() ?? '',
            'project_id' => $record->project_id,
            'project_label' => $this->integration->label($record->project),
            'project_active' => $record->project->active,
            'commentAction' => route('epics.comments.store', $record),
            'commentsUrl' => route('epics.comments.index', $record),
            'commentsCount' => (int) $record->comments_count,
            ...$this->integration->row($record),
        ];
    }

    protected function resourceLabel(): string
    {
        return __('Epics');
    }

    protected function resourceKey(): string
    {
        return 'epic';
    }
}
