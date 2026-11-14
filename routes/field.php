<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:field_auditor'])->prefix('field')->name('field.')->group(function () {
    Route::livewire('/', 'pages::field.index')->name('index');
    Route::livewire('/audits/{audit}', 'pages::field.audit')->name('audit');
});
