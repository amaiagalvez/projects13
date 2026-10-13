<?php

namespace Projects13\Transformers;

use Projects13\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Basics13\Transformers\ListTransformer;
use Projects13\Contracts\ProjectIntegration;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListTransformer<Epic>
 */
class EpicListTransformer extends ListTransformer
{
    public function __construct(private readonly ProjectIntegration $integration) {}

    /**
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $epics, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('active', $search, $counts),
            'rows' => collect($epics->items())->map(function (Epic $epic): array {
                $extra = $this->integration->row($epic);

                return [
                    ...$this->columns($epic, $extra),
                    'extraDate' => $epic->created_at?->toIso8601String(),
                    'actions' => [
                        $this->editAction($epic),
                        $epic->comments_count > 0
                            ? $this->archiveAction($epic, __('basics13::messages.archive_instead_of_delete'))
                            : $this->deleteAction($epic),
                    ],
                ];
            })->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function archived(LengthAwarePaginator $epics, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('archived', $search, $counts),
            'rows' => collect($epics->items())->map(fn (Epic $epic): array => [
                ...$this->columns($epic, $this->integration->row($epic)),
                'extraDate' => $epic->updated_at?->toIso8601String(),
                'actions' => [$this->activateAction($epic)],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $epics, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('trash', $search, $counts),
            'rows' => collect($epics->items())->map(fn (Epic $epic): array => [
                ...$this->columns($epic, $this->integration->row($epic)),
                'extraDate' => $epic->deleted_at?->toIso8601String(),
                'actions' => [
                    $this->restoreAction($epic, $epic->active),
                    $epic->comments_count > 0
                        ? $this->blockedAction(
                            __('Delete permanently'),
                            'lock-closed',
                            'epic-force-delete-blocked-'.$epic->id,
                            __('Cannot be permanently deleted while it has related records.'),
                        )
                        : $this->forceDeleteAction($epic),
                ],
            ])->all(),
        ];
    }

    /**
     * The integration's fields are merged last so it can override anything the base put here,
     * which is the only way a row can carry a column the base knows nothing about.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function columns(Epic $epic, array $extra): array
    {
        return [
            'id' => $epic->id,
            'name' => $epic->name,
            'project' => $epic->project->name,
            'startDate' => $epic->start_date?->format('Y-m-d') ?? '',
            'endDate' => $epic->end_date?->format('Y-m-d') ?? '',
            'commentsCount' => (int) $epic->comments_count,
            'editPayload' => $this->editPayload($epic),
            ...$extra,
        ];
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

    protected function noMatchMessage(): string
    {
        return __('No epics match your search.');
    }

    protected function noRecordsMessage(): string
    {
        return __('No epics yet.');
    }

    protected function resourceKey(): string
    {
        return 'epic';
    }

    /**
     * @return array{active: string, archived: string, trash: string, destroy: string, archive: string, activate: string, restore: string, trashDestroy: string}
     */
    protected function routes(): array
    {
        return [
            'active' => 'epics.index',
            'archived' => 'epics.archived.index',
            'trash' => 'epics.trash.index',
            'destroy' => 'epics.destroy',
            'archive' => 'epics.archive',
            'activate' => 'epics.archived.activate',
            'restore' => 'epics.trash.restore',
            'trashDestroy' => 'epics.trash.destroy',
        ];
    }
}
