<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:head_of_audit|director|system_admin'])->prefix('approvals')->name('approvals.')->group(function () {
    Route::livewire('/', 'pages::approvals.index')->name('index');
    Route::livewire('/audits/{audit}', 'pages::approvals.show')->name('show');
});
