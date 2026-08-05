<?php

use App\Http\Controllers\AiGenerationController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormVersionController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('welcome');

// Kept for Breeze's post-login redirect; landing page differs by role since
// a super owns no forms of their own.
Route::get('/dashboard', function (Request $request) {
    return redirect()->route($request->user()->isSuper() ? 'companies.index' : 'forms.index');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth', 'role:super'])->group(function () {
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::put('/companies/{tenant}', [CompanyController::class, 'update'])->name('companies.update');
    Route::delete('/companies/{tenant}', [CompanyController::class, 'destroy'])->name('companies.destroy');
    Route::post('/companies/{tenant}/restore', [CompanyController::class, 'restore'])->name('companies.restore');
});

Route::middleware(['auth', 'role:tenant'])->group(function () {
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::put('/team/{member}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('/team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
});

Route::middleware(['auth', 'role:tenant,user'])->group(function () {
    Route::resource('forms', FormController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('/forms/{form}/versions', [FormVersionController::class, 'index'])
        ->name('forms.versions.index');
    Route::post('/forms/{form}/versions/{version}/rollback', [FormVersionController::class, 'rollback'])
        ->name('forms.versions.rollback');

    Route::get('/forms/{form}/analytics', [AnalyticsController::class, 'show'])
        ->name('forms.analytics');

    Route::get('/forms/{form}/submissions', [SubmissionController::class, 'index'])
        ->name('forms.submissions.index');
    Route::get('/forms/{form}/submissions/export', [SubmissionController::class, 'export'])
        ->name('forms.submissions.export');
    Route::get('/submission-files/{file}', [SubmissionController::class, 'file'])
        ->name('submissions.file');

    Route::get('/ai/generate', fn () => Inertia::render('Ai/Generate'))
        ->name('ai.generate');
    Route::post('/ai/generations', [AiGenerationController::class, 'store'])
        ->middleware('throttle:ai')->name('ai.generations.store');
    Route::get('/ai/generations/{generation}', [AiGenerationController::class, 'show'])
        ->name('ai.generations.show');

    Route::get('/import', [ImportController::class, 'create'])->name('imports.create');
    Route::post('/import', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/import/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/import/{import}/commit', [ImportController::class, 'commit'])->name('imports.commit');
});

// All three roles can edit their own account. Self-delete is intentionally
// not offered — a tenant deleting themselves would orphan their team and
// cascade-delete their whole company's forms; use the Companies "disable"
// action instead, which preserves data. See super/CompanyController.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Public fill URLs — throttled, no auth.
Route::get('/f/{publicId}', [PublicFormController::class, 'show'])->name('fill.show');
Route::post('/f/{publicId}', [PublicFormController::class, 'store'])
    ->middleware('throttle:fill')->name('fill.store');
Route::post('/f/{publicId}/event', [PublicFormController::class, 'event'])
    ->middleware('throttle:fill-events')->name('fill.event');

require __DIR__.'/auth.php';
