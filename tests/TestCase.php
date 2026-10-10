<?php

namespace Projects13\Tests;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Projects13\Tests\Concerns\ValidatesFormRequests;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Basics13\ServiceProvider as Basics13ServiceProvider;
use Projects13\ServiceProvider as Projects13ServiceProvider;
use Customers13\ServiceProvider as Customers13ServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
    use ValidatesFormRequests;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            Basics13ServiceProvider::class,
            Customers13ServiceProvider::class,
            Projects13ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('view.compiled', dirname(__DIR__).'/storage/framework/views');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }

    protected function defineRoutes($router): void
    {
        Route::get('login', static fn (): string => 'Login')->name('login');
        Route::get('email/verify', static fn (): string => 'Verify email')->name('verification.notice');
        Route::get('dashboard', static fn (): string => 'Dashboard')->name('dashboard');
    }
}
