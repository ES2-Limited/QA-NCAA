<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:system_admin|lead_auditor|head_of_audit|director'])->group(function () {
    Route::livewire('audits', 'pages::audits.index')->name('audits.index');
    Route::livewire('audits/{audit}', 'pages::audits.show')->name('audits.show');
});
