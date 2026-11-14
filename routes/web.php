<?php

use App\Livewire\AuditExecution;
use App\Livewire\AuditorDashboard;
use App\Livewire\FindingReport;
use Illuminate\Support\Facades\Route;

// Redirect root to auditor dashboard
Route::get('/', function () {
    return redirect()->route('auditor.dashboard');
});

// Mobile-First Field Auditor Routes
Route::prefix('auditor')->name('auditor.')->group(function () {
    Route::get('/', AuditorDashboard::class)->name('dashboard');
    Route::get('/audit/{audit}', AuditExecution::class)->name('execute');
    Route::get('/audit/{audit}/finding', FindingReport::class)->name('report-finding');
});
