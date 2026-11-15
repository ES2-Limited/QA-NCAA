<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:field_auditor'])->prefix('field')->name('field.')->group(function () {
    Route::livewire('/', 'pages::field.index')->name('index');
    Route::livewire('/profile', 'pages::field.profile')->name('profile');
    Route::livewire('/notifications', 'pages::field.notifications')->name('notifications');
    Route::livewire('/audits/{audit}', 'pages::field.audit')->name('audit');
});
