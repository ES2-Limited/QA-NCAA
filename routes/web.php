<?php

use App\Livewire\AuditExecution;
use App\Livewire\AuditorDashboard;
use App\Livewire\FindingReport;
use Illuminate\Support\Facades\Route;

// Root / Home route
Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Field Auditor Routes protected by auth
    Route::prefix('auditor')->name('auditor.')->group(function () {
        Route::get('/', AuditorDashboard::class)->name('dashboard');
        Route::get('/audit/{audit}', AuditExecution::class)->name('execute');
        Route::get('/audit/{audit}/finding', FindingReport::class)->name('report-finding');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/processes.php';
