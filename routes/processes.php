<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:system_admin|lead_auditor'])->group(function () {
    Route::livewire('processes', 'pages::processes.index')->name('processes.index');
    Route::livewire('processes/create', 'pages::processes.form')->name('processes.create');
    Route::livewire('processes/{process}', 'pages::processes.show')->name('processes.show');
    Route::livewire('processes/{process}/edit', 'pages::processes.form')->name('processes.edit');
});
