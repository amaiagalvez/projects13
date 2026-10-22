<?php

namespace Projects13\Tests\Unit;

use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ServiceProviderTest extends TestCase
{
    public function test_package_registers_its_routes_and_views(): void
    {
        $this->assertTrue(Route::has('projects.index'));
        $this->assertTrue(Route::has('epics.index'));
        $this->assertTrue(Route::has('planning'));
        $this->assertTrue(Route::has('timeline'));

        $this->assertFileExists(view()->getFinder()->find('projects13::projects.list'));
        $this->assertFileExists(view()->getFinder()->find('projects13::epics.list'));
    }
}
