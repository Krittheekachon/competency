<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CompetencyTypeController as AdminCompetencyTypeController;
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

Route::middleware('auth')->group(function () {
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::patch('/admin/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('admin.users.status');
    Route::post('/admin/competency-types', [AdminCompetencyTypeController::class, 'store'])->name('admin.competency-types.store');
    Route::put('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'update'])->name('admin.competency-types.update');
    Route::delete('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'destroy'])->name('admin.competency-types.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
