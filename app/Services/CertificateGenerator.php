<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateGenerator
{
    public function generate(Audit $audit, ?int $issuedById = null): Certificate
    {
        $issuedBy = $issuedById ?: auth()->id();
        $token = Str::random(32);
        $number = 'DSD-QA-'.date('Y').'-'.str_pad((string) $audit->id, 4, '0', STR_PAD_LEFT);

        $verifyUrl = url('/verify/'.$token);

        // Generate QR code as PNG Data URI
        $qrOptions = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 4,
        ]);
        $qrCodeDataUri = (new QRCode($qrOptions))->render($verifyUrl);

        $totalQuestions = ! empty($audit->questions_snapshot) ? count($audit->questions_snapshot) : ($audit->process ? $audit->process->questions()->count() : 0);
        $totalNcrs = $audit->findings()->count();
        $closedNcrs = $audit->findings()->where('status', 'closed')->count();
        $openNcrs = $totalNcrs - $closedNcrs;

        $headApproval = $audit->approvals()->where('action', 'head_approved')->latest('id')->first();

        $certificate = Certificate::updateOrCreate(
            ['audit_id' => $audit->id],
            [
                'number' => $number,
                'issued_at' => now(),
                'issued_by' => $issuedBy,
                'verify_token' => $token,
            ]
        );

        $pdf = Pdf::loadView('certificates.audit_certificate', [
            'audit' => $audit,
            'certificate' => $certificate,
            'totalQuestions' => $totalQuestions,
            'totalNcrs' => $totalNcrs,
            'closedNcrs' => $closedNcrs,
            'openNcrs' => $openNcrs,
            'headApproval' => $headApproval,
            'qrCodeDataUri' => $qrCodeDataUri,
        ])->setPaper('a4', 'portrait');

        $filePath = 'certificates/'.$certificate->number.'.pdf';
        Storage::disk('public')->put($filePath, $pdf->output());

        $certificate->update(['file_path' => $filePath]);

        return $certificate;
    }
}
