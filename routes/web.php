<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CompetencyController as AdminCompetencyController;
use App\Http\Controllers\Admin\CompetencyTypeController as AdminCompetencyTypeController;
use App\Http\Controllers\Admin\StructureController as AdminStructureController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/admin/dashboard', [DashboardController::class, 'adminIndex'])
    ->middleware(['auth'])
    ->name('admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::patch('/admin/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('admin.users.status');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/competency-types', [AdminCompetencyTypeController::class, 'store'])->name('admin.competency-types.store');
    Route::put('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'update'])->name('admin.competency-types.update');
    Route::delete('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'destroy'])->name('admin.competency-types.destroy');
    Route::post('/admin/competencies', [AdminCompetencyController::class, 'store'])->name('admin.competencies.store');
    Route::put('/admin/competencies/{competency}', [AdminCompetencyController::class, 'update'])->name('admin.competencies.update');
    Route::delete('/admin/competencies/{competency}', [AdminCompetencyController::class, 'destroy'])->name('admin.competencies.destroy');
    Route::post('/admin/structure/worklines', [AdminStructureController::class, 'storeWorkline'])->name('admin.structure.worklines.store');
    Route::put('/admin/structure/worklines', [AdminStructureController::class, 'updateWorkline'])->name('admin.structure.worklines.update');
    Route::delete('/admin/structure/worklines', [AdminStructureController::class, 'destroyWorkline'])->name('admin.structure.worklines.destroy');
    Route::post('/admin/structure/job-families', [AdminStructureController::class, 'storeJobFamily'])->name('admin.structure.job-families.store');
    Route::put('/admin/structure/job-families', [AdminStructureController::class, 'updateJobFamily'])->name('admin.structure.job-families.update');
    Route::delete('/admin/structure/job-families', [AdminStructureController::class, 'destroyJobFamily'])->name('admin.structure.job-families.destroy');
    Route::post('/admin/structure/positions', [AdminStructureController::class, 'storePosition'])->name('admin.structure.positions.store');
    Route::put('/admin/structure/positions', [AdminStructureController::class, 'updatePosition'])->name('admin.structure.positions.update');
    Route::delete('/admin/structure/positions', [AdminStructureController::class, 'destroyPosition'])->name('admin.structure.positions.destroy');
    Route::post('/admin/structure/levels', [AdminStructureController::class, 'storeLevel'])->name('admin.structure.levels.store');
    Route::put('/admin/structure/levels', [AdminStructureController::class, 'updateLevel'])->name('admin.structure.levels.update');
    Route::delete('/admin/structure/levels', [AdminStructureController::class, 'destroyLevel'])->name('admin.structure.levels.destroy');
    Route::post('/admin/structure/learning-methods', [AdminStructureController::class, 'storeLearningMethod'])->name('admin.structure.learning-methods.store');
    Route::put('/admin/structure/learning-methods', [AdminStructureController::class, 'updateLearningMethod'])->name('admin.structure.learning-methods.update');
    Route::delete('/admin/structure/learning-methods', [AdminStructureController::class, 'destroyLearningMethod'])->name('admin.structure.learning-methods.destroy');
    Route::post('/admin/structure/support-depts', [AdminStructureController::class, 'storeSupportDept'])->name('admin.structure.support-depts.store');
    Route::post('/admin/structure/support-works', [AdminStructureController::class, 'storeSupportWork'])->name('admin.structure.support-works.store');
    Route::post('/admin/structure/support-units', [AdminStructureController::class, 'storeSupportUnit'])->name('admin.structure.support-units.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
