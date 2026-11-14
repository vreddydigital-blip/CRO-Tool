<?php

use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanHistoryController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/primary', [ProjectController::class, 'setPrimary'])->name('projects.primary');

    // Scans
    Route::post('projects/{project}/scan', [ScanController::class, 'store'])->name('scans.store');
    Route::get('scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
    Route::delete('scans/{scan}', [ScanController::class, 'destroy'])->name('scans.destroy');

    // Findings / recommendations / history
    Route::get('findings', [FindingController::class, 'index'])->name('findings.index');
    Route::get('findings/{finding}', [FindingController::class, 'show'])->name('findings.show');
    Route::get('recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
    Route::get('history', [ScanHistoryController::class, 'index'])->name('history.index');

    // Competitors
    Route::get('projects/{project}/competitors', [CompetitorController::class, 'index'])->name('competitors.index');
    Route::post('projects/{project}/competitors', [CompetitorController::class, 'store'])->name('competitors.store');
    Route::post('projects/{project}/competitors/{competitor}/analyze', [CompetitorController::class, 'analyze'])->name('competitors.analyze');
    Route::delete('projects/{project}/competitors/{competitor}', [CompetitorController::class, 'destroy'])->name('competitors.destroy');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('scans/{scan}/report', [ReportController::class, 'download'])->name('reports.download');
    Route::get('scans/{scan}/report/view', [ReportController::class, 'stream'])->name('reports.stream');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
