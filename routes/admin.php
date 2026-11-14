<?php

use App\Models\Document;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Protected system admin routes
Route::middleware(['auth', 'role:system_admin'])->group(function () {
    Route::redirect('admin', 'admin/directorates');

    Route::livewire('admin/directorates', 'pages::admin.directorates')->name('admin.directorates');
    Route::livewire('admin/departments', 'pages::admin.departments')->name('admin.departments');
    Route::livewire('admin/units', 'pages::admin.units')->name('admin.units');
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
});

// Document Library accessible to system_admin and lead_auditor
Route::middleware(['auth', 'role:system_admin|lead_auditor'])->group(function () {
    Route::livewire('admin/documents', 'pages::admin.documents')->name('admin.documents');

    Route::get('admin/documents/{document}/download', function (Document $document) {
        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found on server.');
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type]
        );
    })->name('admin.documents.download');
});
