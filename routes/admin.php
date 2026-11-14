<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:system_admin'])->group(function () {
    Route::redirect('admin', 'admin/directorates');

    Route::livewire('admin/directorates', 'pages::admin.directorates')->name('admin.directorates');
    Route::livewire('admin/departments', 'pages::admin.departments')->name('admin.departments');
    Route::livewire('admin/units', 'pages::admin.units')->name('admin.units');
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
});
