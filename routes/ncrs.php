<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:ncrs.view'])->prefix('ncrs')->name('ncrs.')->group(function () {
    Route::livewire('/', 'pages::ncrs.index')->name('index');
    Route::livewire('/{ncr}', 'pages::ncrs.show')->name('show');
});
