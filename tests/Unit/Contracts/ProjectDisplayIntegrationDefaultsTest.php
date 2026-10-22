<?php

namespace Projects13\Tests\Unit\Contracts;

use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectDisplayIntegrationDefaults;

class ProjectDisplayIntegrationDefaultsTest extends TestCase
{
    private ProjectDisplayIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new class implements ProjectDisplayIntegration
        {
            use ProjectDisplayIntegrationDefaults;
        };
    }

    public function test_label_returns_project_name(): void
    {
        $project = new Project(['name' => 'Test Project']);

        $result = $this->integration->label($project);

        $this->assertSame('Test Project', $result);
    }

    public function test_trail_returns_empty_array(): void
    {
        $project = new Project(['name' => 'Test Project']);

        $result = $this->integration->trail($project);

        $this->assertSame([], $result);
    }

    public function test_row_returns_empty_array(): void
    {
        $project = new Project(['name' => 'Test Project']);

        $result = $this->integration->row($project);

        $this->assertSame([], $result);
    }

    public function test_has_active_related_entities_returns_false(): void
    {
        $result = $this->integration->hasActiveRelated();

        $this->assertFalse($result);
    }

    public function test_no_related_entities_banner_returns_null(): void
    {
        $result = $this->integration->noRelatedBanner();

        $this->assertNull($result);
    }
}
