<?php

namespace Projects13\Transformers;

use Projects13\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Basics13\Transformers\ListTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Projects13\Contracts\ProjectDisplayIntegration;

/**
 * @extends ListTransformer<Project>
 */
class ProjectListTransformer extends ListTransformer
{
    public function __construct(private readonly ProjectDisplayIntegration $integration) {}

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('active', $search, $counts),
            'rows' => collect($projects->items())->map(function (Project $project): array {
                $extra = $this->integration->row($project);

                return [
                    ...$this->columns($project, $extra),
                    'extraDate' => $project->created_at?->toIso8601String(),
                    'actions' => [
                        $this->editAction($project),
                        $project->epics_exists
                            ? $this->archiveAction($project, __('basics13::messages.archive_instead_of_delete'))
                            : $this->deleteAction($project),
                    ],
                ];
            })->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function archived(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('archived', $search, $counts),
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project, $this->integration->row($project)),
                'extraDate' => $project->updated_at?->toIso8601String(),
                'actions' => [$this->activateAction($project)],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('trash', $search, $counts),
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project, $this->integration->row($project)),
                'extraDate' => $project->deleted_at?->toIso8601String(),
                'actions' => [
                    $this->restoreAction($project, $project->active),
                    $project->epics_count > 0
                        ? $this->blockedAction(
                            __('basics13::messages.delete_permanently'),
                            'lock-closed',
                            'project-force-delete-blocked-'.$project->id,
                            __('basics13::messages.cannot_force_delete_related'),
                        )
                        : $this->forceDeleteAction($project),
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
    private function columns(Project $project, array $extra): array
    {
        $epicsCount = (int) $project->epics_count;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'startDate' => $project->start_date->format('Y-m-d'),
            'endDate' => $project->end_date?->format('Y-m-d') ?? '',
            'epicsCount' => $epicsCount,
            // The epics list searches project names, so the row count can link to its own slice.
            'epicsUrl' => $epicsCount > 0
                ? route('epics.index', ['search' => $project->name])
                : null,
            'commentsCount' => (int) $project->comments_count,
            'editPayload' => [...$this->editPayload($project), ...$extra],
            ...$extra,
        ];
    }

    /**
     * The same extra fields land in the drawer payload, so a row and a drawer reopened from it
     * always carry the same keys.
     *
     * @param  Project  $record
     * @return array<string, mixed>
     */
    protected function editPayload(Model $record): array
    {
        $extra = $this->integration->row($record);

        return [
            'id' => $record->id,
            'name' => $record->name,
            'notes' => $record->notes,
            'start_date' => $record->start_date->toDateString(),
            'end_date' => $record->end_date?->toDateString() ?? '',
            ...$extra,
        ];
    }

    protected function resourceLabel(): string
    {
        return __('Projects');
    }

    protected function noMatchMessage(): string
    {
        return __('No projects match your search.');
    }

    protected function noRecordsMessage(): string
    {
        return __('No projects yet.');
    }

    protected function resourceKey(): string
    {
        return 'project';
    }

    /**
     * @return array{active: string, archived: string, trash: string, destroy: string, archive: string, activate: string, restore: string, trashDestroy: string}
     */
    protected function routes(): array
    {
        return [
            'active' => 'projects.index',
            'archived' => 'projects.archived.index',
            'trash' => 'projects.trash.index',
            'destroy' => 'projects.destroy',
            'archive' => 'projects.archive',
            'activate' => 'projects.archived.activate',
            'restore' => 'projects.trash.restore',
            'trashDestroy' => 'projects.trash.destroy',
        ];
    }
}
