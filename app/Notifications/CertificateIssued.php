<?php

namespace App\Notifications;

use App\Models\Audit;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;

class CertificateIssued extends BaseNotification
{
    use Queueable;

    public function __construct(
        public Audit $audit,
        public Certificate $certificate
    ) {}

    public function eventKey(): string
    {
        return 'certificate_issued';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'Official Quality Audit Certificate Issued: {certificate_number}',
            'body' => "Official Quality Audit Certificate {certificate_number} has been issued for audit {audit_title} ({audit_ref}) on {issue_date}.\nDownload or verify via the NCAA QA portal.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        return [
            'certificate_number' => $this->certificate->number,
            'audit_title' => $this->audit->title,
            'audit_ref' => $this->audit->reference_number,
            'issue_date' => $this->certificate->issued_at?->format('M d, Y') ?? now()->format('M d, Y'),
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        return route('audits.show', $this->audit->id);
    }
}
