<?php

namespace Projects13\Transformers;

use Projects13\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Basics13\Transformers\ListTransformer;
use Projects13\Contracts\ProjectDisplayIntegration;

/**
 * @extends ListTransformer<Project>
 */
class ProjectListTransformer extends ListTransformer
{
    public function __construct(private readonly ProjectDisplayIntegration $integration) {}

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function columns(Model $record, array $extra): array
    {
        $epicsCount = (int) $record->epics_count;

        return [
            'id' => $record->id,
            'name' => $record->name,
            'startDate' => $record->start_date->format('Y-m-d'),
            'endDate' => $record->end_date?->format('Y-m-d') ?? '',
            'epicsCount' => $epicsCount,
            // The epics list searches project names, so the row count can link to its own slice.
            'epicsUrl' => $epicsCount > 0
                ? route('epics.index', ['search' => $record->name])
                : null,
            'commentsCount' => (int) $record->comments_count,
            'editPayload' => [...$this->editPayload($record), ...$extra],
            ...$extra,
        ];
    }

    protected function getDeleteBlockCondition(Model $record): bool
    {
        return (bool) $record->epics_exists;
    }

    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return $record->epics_count > 0;
    }

    protected function getForceDeleteBlockHint(Model $record): string
    {
        return __('basics13::messages.cannot_force_delete_related');
    }

    protected function integrationRow(Model $record): array
    {
        return $this->integration->row($record);
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

    protected function resourceKey(): string
    {
        return 'project';
    }
}
