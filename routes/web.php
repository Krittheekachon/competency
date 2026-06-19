<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CompetencyController as AdminCompetencyController;
use App\Http\Controllers\Admin\CompetencyTypeController as AdminCompetencyTypeController;
use App\Http\Controllers\Admin\IdpLearningMethodController as AdminIdpLearningMethodController;
use App\Http\Controllers\Admin\LearningCatalogController as AdminLearningCatalogController;
use App\Http\Controllers\Admin\StructureController as AdminStructureController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hr\CompetencyAssignmentController as HrCompetencyAssignmentController;
use App\Http\Controllers\MockSsoController;
use App\Http\Controllers\Hr\PositionCompetencyController as HrPositionCompetencyController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'pageTitle' => 'เข้าสู่ระบบ',
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

if (app()->environment('local')) {
    Route::get('/mock-sso', [MockSsoController::class, 'showLogin'])->name('mock.sso');
    Route::post('/mock-sso', [MockSsoController::class, 'login'])->name('mock.sso.login');
}

Route::middleware('auth')->group(function () {
    Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::patch('/admin/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('admin.users.status');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/competency-types', [AdminCompetencyTypeController::class, 'store'])->name('admin.competency-types.store');
    Route::put('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'update'])->name('admin.competency-types.update');
    Route::delete('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'destroy'])->name('admin.competency-types.destroy');
    Route::post('/admin/competencies', [AdminCompetencyController::class, 'store'])->name('admin.competencies.store');
    Route::post('/admin/competencies/import', [AdminCompetencyController::class, 'import'])->name('admin.competencies.import');
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
    Route::post('/admin/structure/support-departments', [AdminStructureController::class, 'storeSupportDepartment'])->name('admin.structure.support-departments.store');
    Route::put('/admin/structure/support-departments', [AdminStructureController::class, 'updateSupportDepartment'])->name('admin.structure.support-departments.update');
    Route::delete('/admin/structure/support-departments', [AdminStructureController::class, 'destroySupportDepartment'])->name('admin.structure.support-departments.destroy');
    Route::post('/admin/structure/support-works', [AdminStructureController::class, 'storeSupportWork'])->name('admin.structure.support-works.store');
    Route::put('/admin/structure/support-works', [AdminStructureController::class, 'updateSupportWork'])->name('admin.structure.support-works.update');
    Route::delete('/admin/structure/support-works', [AdminStructureController::class, 'destroySupportWork'])->name('admin.structure.support-works.destroy');
    Route::post('/admin/structure/support-units', [AdminStructureController::class, 'storeSupportUnit'])->name('admin.structure.support-units.store');
    Route::put('/admin/structure/support-units', [AdminStructureController::class, 'updateSupportUnit'])->name('admin.structure.support-units.update');
    Route::delete('/admin/structure/support-units', [AdminStructureController::class, 'destroySupportUnit'])->name('admin.structure.support-units.destroy');
    Route::post('/admin/structure/levels', [AdminStructureController::class, 'storeLevel'])->name('admin.structure.levels.store');
    Route::put('/admin/structure/levels', [AdminStructureController::class, 'updateLevel'])->name('admin.structure.levels.update');
    Route::delete('/admin/structure/levels', [AdminStructureController::class, 'destroyLevel'])->name('admin.structure.levels.destroy');
    Route::post('/admin/structure/learning-methods', [AdminStructureController::class, 'storeLearningMethod'])->name('admin.structure.learning-methods.store');
    Route::put('/admin/structure/learning-methods', [AdminStructureController::class, 'updateLearningMethod'])->name('admin.structure.learning-methods.update');
    Route::delete('/admin/structure/learning-methods', [AdminStructureController::class, 'destroyLearningMethod'])->name('admin.structure.learning-methods.destroy');
    Route::post('/hr/competency-assignments', [HrCompetencyAssignmentController::class, 'store'])->name('hr.competency-assignments.store');
    Route::post('/assessments/draft', [AssessmentController::class, 'draft'])->name('assessments.draft');
    Route::post('/assessments/save', [AssessmentController::class, 'save'])->name('assessments.save');
    Route::get('/assessments/load', [AssessmentController::class, 'load'])->name('assessments.load');
    Route::post('/assessments/approve', [AssessmentController::class, 'approve'])->name('assessments.approve');
    Route::post('/assessments/reject', [AssessmentController::class, 'reject'])->name('assessments.reject');
    Route::post('/admin/idp-learning-methods', [AdminIdpLearningMethodController::class, 'store'])->name('admin.idp-learning-methods.store');
    Route::put('/admin/idp-learning-methods/{method}', [AdminIdpLearningMethodController::class, 'update'])->name('admin.idp-learning-methods.update');
    Route::delete('/admin/idp-learning-methods/{method}', [AdminIdpLearningMethodController::class, 'destroy'])->name('admin.idp-learning-methods.destroy');
    Route::post('/admin/learning-catalogs', [AdminLearningCatalogController::class, 'store'])->name('admin.learning-catalogs.store');
    Route::put('/admin/learning-catalogs/{catalog}', [AdminLearningCatalogController::class, 'update'])->name('admin.learning-catalogs.update');
    Route::delete('/admin/learning-catalogs/{catalog}', [AdminLearningCatalogController::class, 'destroy'])->name('admin.learning-catalogs.destroy');
    Route::post('/hr/position-competencies', [HrPositionCompetencyController::class, 'store'])->name('hr.position-competencies.store');
    Route::delete('/hr/position-competencies', [HrPositionCompetencyController::class, 'destroy'])->name('hr.position-competencies.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
