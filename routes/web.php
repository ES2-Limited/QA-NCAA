<?php

use App\Models\Attachment;
use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\Certificate;
use App\Models\CorrectiveActionPlan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Mechanisms\HandleRouting\LivewirePageController;

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

        // system_admin, lead_auditor, head_of_audit, director → Livewire management dashboard
        return app(LivewirePageController::class)
            ->__invoke(tap(request()->route(), fn ($r) => $r->setAction(
                array_merge($r->getAction(), ['livewire_component' => 'pages::dashboard'])
            )));
    })->name('dashboard');

    // Certificate Download
    Route::get('certificates/{audit}/download', function (Audit $audit) {
        $cert = $audit->certificate;
        if (! $cert || ! $cert->file_path || ! Storage::disk('public')->exists($cert->file_path)) {
            abort(404, 'Certificate not found.');
        }

        return Storage::disk('public')->download($cert->file_path, "{$cert->number}.pdf");
    })->name('certificates.download');

    // Secure Evidence / Attachment download & viewing
    Route::get('attachments/{attachment}/download', function (Attachment $attachment) {
        $user = auth()->user();

        // Authorize via parent model
        $attachable = $attachment->attachable;
        if ($attachable instanceof AuditAnswer) {
            $audit = $attachable->audit;
            if ($audit && ! $user->can('view', $audit)) {
                abort(403);
            }
        } elseif ($attachable instanceof CorrectiveActionPlan) {
            $finding = $attachable->ncr;
            if ($finding && ! $user->can('view', $finding)) {
                abort(403);
            }
        }

        $disk = Storage::disk('local')->exists($attachment->path) ? 'local' : 'public';
        if (! Storage::disk($disk)->exists($attachment->path)) {
            abort(404, 'File not found on server.');
        }

        return Storage::disk($disk)->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    })->name('attachments.show');
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
