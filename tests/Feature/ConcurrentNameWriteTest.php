<?php

namespace Projects13\Tests\Feature;

use PDOException;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Customers13\Models\Customer;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConcurrentNameWriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: 'project'|'epic', 1: 'store'|'update'|'restore'}>
     */
    public static function writes(): array
    {
        return [
            'project store' => ['project', 'store'],
            'project update' => ['project', 'update'],
            'project restore' => ['project', 'restore'],
            'epic store' => ['epic', 'store'],
            'epic update' => ['epic', 'update'],
            'epic restore' => ['epic', 'restore'],
        ];
    }

    /**
     * @param  'project'|'epic'  $resource
     * @param  'store'|'update'|'restore'  $operation
     */
    #[DataProvider('writes')]
    public function test_unique_constraint_race_returns_error_without_changing_record(string $resource, string $operation): void
    {
        $this->signIn();
        $record = match ($resource) {
            'project' => Project::factory()->create(['name' => 'Original project']),
            'epic' => Epic::factory()->withoutDates()->create(['name' => 'Original epic']),
        };
        $payload = match (true) {
            $record instanceof Project => [
                'name' => 'Concurrent project',
                'customer_id' => $record->customer_id,
                'start_date' => '2026-01-01',
            ],
            default => ['name' => 'Concurrent epic', 'project_id' => $record->project_id],
        };
        $prefix = $resource.'s';
        $originalName = $record->name;
        $exception = new PDOException('Duplicate name');
        $exception->errorInfo = ['23000', 19, 'UNIQUE constraint failed'];
        $failure = static function () use ($exception): never {
            throw new QueryException('sqlite', 'write', [], $exception);
        };

        if ($operation === 'restore') {
            $record->delete();
            $record::updating($failure);

            $this->patch(route($prefix.'.trash.restore', $record))
                ->assertRedirect(route($prefix.'.trash.index'))
                ->assertSessionHas('error', __('basics13::messages.cannot_restore_name_taken'));
            $this->assertSoftDeleted($record);
        } elseif ($operation === 'update') {
            $record::updating($failure);

            $this->put(route($prefix.'.update', $record), $payload)
                ->assertSessionHasErrors(['name' => __('validation.unique', ['attribute' => __('Name')])]);
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id, 'name' => $originalName]);
        } else {
            $record::creating($failure);

            $this->post(route($prefix.'.store'), $payload)
                ->assertSessionHasErrors(['name' => __('validation.unique', ['attribute' => __('Name')])]);
        }

        $this->assertDatabaseCount($record->getTable(), 1);
        $this->assertDatabaseCount((new Customer)->getTable(), 1);
    }
}
