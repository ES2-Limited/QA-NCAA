<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:audits.view'])->group(function () {
    Route::livewire('audits', 'pages::audits.index')->name('audits.index');
    Route::livewire('audits/{audit}', 'pages::audits.show')->name('audits.show');
});
