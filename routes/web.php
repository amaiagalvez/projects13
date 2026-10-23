<?php

use Illuminate\Support\Facades\Route;
use Projects13\Http\Controllers\EpicController;
use Projects13\Http\Controllers\ProjectController;
use Projects13\Http\Controllers\PlanningController;
use Projects13\Http\Controllers\TimelineController;
use Projects13\Http\Controllers\EpicTrashController;
use Projects13\Http\Controllers\EpicCommentController;
use Projects13\Http\Controllers\EpicArchivedController;
use Projects13\Http\Controllers\ProjectTrashController;
use Projects13\Http\Controllers\ProjectMemberController;
use Projects13\Http\Controllers\ProjectArchivedController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('planning', [PlanningController::class, 'index'])->name('planning');
    Route::get('timeline', [TimelineController::class, 'index'])->name('timeline');

    Route::get('projects/archived', [ProjectArchivedController::class, 'index'])->name('projects.archived.index');
    Route::patch('projects/archived/{project}', [ProjectArchivedController::class, 'activate'])
        ->whereNumber('project')
        ->name('projects.archived.activate');
    Route::patch('projects/{project}/archive', [ProjectArchivedController::class, 'archive'])
        ->whereNumber('project')
        ->name('projects.archive');
    Route::get('projects/trash', [ProjectTrashController::class, 'index'])->name('projects.trash.index');
    Route::patch('projects/trash/{project}', [ProjectTrashController::class, 'restore'])
        ->whereNumber('project')
        ->name('projects.trash.restore');
    Route::delete('projects/trash/{project}', [ProjectTrashController::class, 'destroy'])
        ->whereNumber('project')
        ->name('projects.trash.destroy');
    Route::get('projects/options', [ProjectController::class, 'selectOptions'])->name('projects.options');
    Route::get('projects/export', [ProjectController::class, 'export'])->name('projects.export');
    Route::resource('projects', ProjectController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('projects/{project}/members', [ProjectMemberController::class, 'index'])
        ->whereNumber('project')
        ->name('projects.members.index');
    Route::get('projects/{project}/members/candidates', [ProjectMemberController::class, 'candidates'])
        ->whereNumber('project')
        ->name('projects.members.candidates');
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])
        ->whereNumber('project')
        ->name('projects.members.store');
    Route::patch('projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])
        ->whereNumber('project')
        ->whereNumber('member')
        ->name('projects.members.update');
    Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])
        ->whereNumber('project')
        ->whereNumber('member')
        ->name('projects.members.destroy');

    Route::get('epics/archived', [EpicArchivedController::class, 'index'])->name('epics.archived.index');
    Route::patch('epics/archived/{epic}', [EpicArchivedController::class, 'activate'])
        ->whereNumber('epic')
        ->name('epics.archived.activate');
    Route::patch('epics/{epic}/archive', [EpicArchivedController::class, 'archive'])
        ->whereNumber('epic')
        ->name('epics.archive');
    Route::get('epics/trash', [EpicTrashController::class, 'index'])->name('epics.trash.index');
    Route::patch('epics/trash/{epic}', [EpicTrashController::class, 'restore'])
        ->whereNumber('epic')
        ->name('epics.trash.restore');
    Route::delete('epics/trash/{epic}', [EpicTrashController::class, 'destroy'])
        ->whereNumber('epic')
        ->name('epics.trash.destroy');
    Route::get('epics/{epic}/comments', [EpicCommentController::class, 'show'])
        ->whereNumber('epic')
        ->name('epics.comments.index');
    Route::post('epics/{epic}/comments', [EpicCommentController::class, 'store'])
        ->whereNumber('epic')
        ->name('epics.comments.store');
    Route::get('epics/export', [EpicController::class, 'export'])->name('epics.export');
    Route::resource('epics', EpicController::class)->only(['index', 'store', 'update', 'destroy']);
});
