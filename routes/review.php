<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:audits.review'])->prefix('review')->name('review.')->group(function () {
    Route::livewire('/', 'pages::review.index')->name('index');
    Route::livewire('/audits/{audit}', 'pages::review.show')->name('show');
});
