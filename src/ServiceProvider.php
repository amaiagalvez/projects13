<?php

namespace Projects13;

use Projects13\Models\Epic;
use Projects13\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'projects13');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->blockDeletionWithChildren();
    }

    public function register(): void
    {
        //
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

            return ! $locked->comments()->exists();
        });
    }
}
