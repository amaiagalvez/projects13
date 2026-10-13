<?php

namespace Projects13\Tests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\FluxServiceProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Basics13\Support\Testing\ValidatesFormRequests;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Basics13\ServiceProvider as Basics13ServiceProvider;
use Projects13\ServiceProvider as Projects13ServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
    use RefreshDatabase, ValidatesFormRequests;

    protected function signIn(): User
    {
        $user = User::query()->create([
            'name' => 'Test author',
            'email' => 'author@example.test',
            'password' => 'password',
        ]);

        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            Basics13ServiceProvider::class,
            Projects13ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        Date::use(CarbonImmutable::class);

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

    protected function setUp(): void
    {
        parent::setUp();

        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views/layouts', 'layouts');
    }
}
