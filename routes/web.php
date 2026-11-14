<?php

use Illuminate\Support\Facades\Route;

// Root / Home route: field auditors land on /field, others on dashboard
Route::get('/', function () {
    if (auth()->check() && auth()->user()->hasRole('field_auditor')) {
        return redirect()->route('field.index');
    }

    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        if (auth()->user()->hasRole('field_auditor')) {
            return redirect()->route('field.index');
        }

        return view('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/processes.php';
require __DIR__.'/audits.php';
require __DIR__.'/field.php';
