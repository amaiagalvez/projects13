<?php

namespace Projects13\Tests;

use App\Models\User;
use Basics13\Models\Role;
use Carbon\CarbonImmutable;
use Flux\FluxServiceProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Blade;
use Basics13\Tests\TestCase as Basics13TestCase;
use Basics13\Support\Testing\ValidatesFormRequests;
use Projects13\ServiceProvider as Projects13ServiceProvider;

abstract class TestCase extends Basics13TestCase
{
    use ValidatesFormRequests;

    protected function signIn(): User
    {
        $user = User::query()->create([
            'name' => 'Test author',
            'email' => 'author@example.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::query()->where('slug', 'admin')->firstOrFail());

        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            FluxServiceProvider::class,
            Projects13ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        Date::use(CarbonImmutable::class);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('view.compiled', dirname(__DIR__).'/storage/framework/views');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views/layouts', 'layouts');
    }
}
