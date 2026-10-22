<?php

namespace Projects13\Tests\Unit;

use Projects13\Models\Project;
use Basics13\Tests\PackageIsolationTestCase;

/**
 * projects13 must work without entities13 or the bridge installed.
 */
class PackageIsolationTest extends PackageIsolationTestCase
{
    protected function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function forbiddenInProductionCode(): array
    {
        return [
            'App\\',
            'EntityProjects13',
            'entities13',
            'ENT_entities',
            'entity_id',
            "route('entities.",
            "to_route('entities.",
            "'entities.index'",
            "'entities.store'",
            "'entities.update'",
            "'entities.destroy'",
            "'entities.archive'",
            "'entities.archived",
            "'entities.trash",
            "'entities.options'",
        ];
    }

    protected function forbiddenInTests(): array
    {
        return [
            'Entities13\\',
            'EntityProjects13\\',
            'entity_id',
        ];
    }

    protected function isolationAdvice(): string
    {
        return 'projects13 must work without entities13 installed, but its production code still '
            .'names it. Move whatever needs an entity into the integration package, and keep the '
            .'contracts in Projects13\\Contracts\\ProjectValidationIntegration, '
            .'ProjectQueryIntegration, ProjectDisplayIntegration as the only seams.';
    }

    /**
     * The model is where a forbidden relation would first show up, and this reads the contract
     * rather than the model: a project has no entity until an integration gives it one.
     */
    public function test_the_project_model_declares_no_entity(): void
    {
        $this->assertFalse(method_exists(Project::class, 'entity'));
        $this->assertFalse(method_exists(Project::class, 'fullName'));
    }
}
