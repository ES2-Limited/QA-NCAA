<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:auditee|lead_auditor|reviewer|head_of_audit|director|system_admin'])->prefix('ncrs')->name('ncrs.')->group(function () {
    Route::livewire('/', 'pages::ncrs.index')->name('index');
    Route::livewire('/{ncr}', 'pages::ncrs.show')->name('show');
});
