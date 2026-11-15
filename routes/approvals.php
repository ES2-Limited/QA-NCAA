<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:approvals.endorse|approvals.final'])->prefix('approvals')->name('approvals.')->group(function () {
    Route::livewire('/', 'pages::approvals.index')->name('index');
    Route::livewire('/audits/{audit}', 'pages::approvals.show')->name('show');
});
