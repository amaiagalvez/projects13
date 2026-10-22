<?php

namespace Projects13;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Support\Facades\DB;
use Projects13\Contracts\ProjectQueryIntegration;
use Projects13\Contracts\DefaultProjectIntegration;
use Projects13\Contracts\ProjectDisplayIntegration;
use Projects13\Contracts\ProjectValidationIntegration;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'projects13');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'projects13');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../config/projects13.php' => config_path('projects13.php'),
        ], 'projects13-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/projects13'),
        ], 'projects13-views');

        $this->blockDeletionWithChildren();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/projects13.php', 'projects13');

        // Bound only if nobody else has: an integration package registers after this one and
        // replaces it, and doing so does not depend on Composer's provider order being right.
        $this->app->singletonIf(ProjectValidationIntegration::class, DefaultProjectIntegration::class);
        $this->app->singletonIf(ProjectQueryIntegration::class, DefaultProjectIntegration::class);
        $this->app->singletonIf(ProjectDisplayIntegration::class, DefaultProjectIntegration::class);
    }

    protected function blockDeletionWithChildren(): void
    {
        Project::deleting(function (Project $project): bool {
            $locked = DB::transaction(static fn (): Project => Project::withTrashed()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            return ! $locked->epics()->withTrashed()->exists();
        });

        Epic::deleting(function (Epic $epic): bool {
            $locked = DB::transaction(static fn (): Epic => Epic::withTrashed()
                ->whereKey($epic->getKey())
                ->lockForUpdate()
                ->firstOrFail());

            // Trashed comments count too, as trashed children do for every parent guard.
            return ! $locked->comments()->withTrashed()->exists();
        });
    }
}
