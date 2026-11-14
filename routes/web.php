<?php

use App\Models\Audit;
use App\Models\Certificate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Root / Home route: landing redirection based on role
Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->hasRole('field_auditor')) {
            return redirect()->route('field.index');
        }
        if ($user->hasRole('reviewer')) {
            return redirect()->route('review.index');
        }
        if ($user->hasRole('auditee')) {
            return redirect()->route('ncrs.index');
        }
        if ($user->hasRole('head_of_audit') || $user->hasRole('director')) {
            return redirect()->route('approvals.index');
        }
    }

    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        if ($user->hasRole('field_auditor')) {
            return redirect()->route('field.index');
        }
        if ($user->hasRole('reviewer')) {
            return redirect()->route('review.index');
        }
        if ($user->hasRole('auditee')) {
            return redirect()->route('ncrs.index');
        }
        if ($user->hasRole('head_of_audit') || $user->hasRole('director')) {
            return redirect()->route('approvals.index');
        }

        return view('dashboard');
    })->name('dashboard');

    // Certificate Download
    Route::get('certificates/{audit}/download', function (Audit $audit) {
        $cert = $audit->certificate;
        if (! $cert || ! $cert->file_path || ! Storage::disk('public')->exists($cert->file_path)) {
            abort(404, 'Certificate not found.');
        }

        return Storage::disk('public')->download($cert->file_path, "{$cert->number}.pdf");
    })->name('certificates.download');
});

// Public Certificate Verification (no auth)
Route::get('verify/{token}', function (string $token) {
    $cert = Certificate::with(['audit.directorate', 'audit.process.unit'])->where('verify_token', $token)->first();

    return view('pages.verify', [
        'valid' => $cert !== null,
        'certificate' => $cert,
    ]);
})->name('certificates.verify');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/processes.php';
require __DIR__.'/audits.php';
require __DIR__.'/field.php';
require __DIR__.'/review.php';
require __DIR__.'/ncrs.php';
require __DIR__.'/approvals.php';
