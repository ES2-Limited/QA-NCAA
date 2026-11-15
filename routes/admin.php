<?php

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Permission-aware redirect for /admin
Route::middleware(['auth'])->get('admin', function () {
    if (auth()->user()->can('settings.manage') || auth()->user()->can('users.manage')) {
        return redirect()->route('admin.directorates');
    }

    if (auth()->user()->can('documents.view')) {
        return redirect()->route('admin.documents');
    }

    abort(403);
});

// Protected system admin routes
Route::middleware(['auth', 'permission:settings.manage|users.manage'])->group(function () {
    Route::livewire('admin/directorates', 'pages::admin.directorates')->name('admin.directorates');
    Route::livewire('admin/departments', 'pages::admin.departments')->name('admin.departments');
    Route::livewire('admin/units', 'pages::admin.units')->name('admin.units');
    Route::livewire('admin/lookups', 'pages::admin.lookups')->name('admin.lookups');
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
    Route::livewire('admin/roles', 'pages::admin.roles')->name('admin.roles');
    Route::livewire('admin/settings', 'pages::admin.settings')->name('admin.settings');
    Route::livewire('admin/workflow', 'pages::admin.workflow')->name('admin.workflow');
});

// Document Library accessible to users with documents.view
Route::middleware(['auth', 'permission:documents.view'])->group(function () {
    Route::livewire('admin/documents', 'pages::admin.documents')->name('admin.documents');

    Route::get('admin/documents/{document}/download', function (Document $document) {
        Gate::authorize('download', $document);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found on server.');
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type]
        );
    })->name('admin.documents.download');
});
