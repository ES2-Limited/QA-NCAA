<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:processes.view'])->group(function () {
    Route::livewire('processes', 'pages::processes.index')->name('processes.index');
    Route::livewire('processes/create', 'pages::processes.form')->middleware('permission:processes.create')->name('processes.create');
    Route::livewire('processes/{process}', 'pages::processes.show')->name('processes.show');
    Route::livewire('processes/{process}/edit', 'pages::processes.form')->middleware('permission:processes.edit')->name('processes.edit');
});
