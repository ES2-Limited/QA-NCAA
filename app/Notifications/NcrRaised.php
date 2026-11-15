<?php

namespace App\Notifications;

use App\Models\Finding;
use Illuminate\Bus\Queueable;

class NcrRaised extends BaseNotification
{
    use Queueable;

    public function __construct(
        public Finding $finding
    ) {}

    public function eventKey(): string
    {
        return 'ncr_raised';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'Action Required: Level {level} NCR Raised - {ncr_ref}',
            'body' => "A Level {level} Non-Conformance Report ({ncr_ref}) was issued for {audit_title}.\nRequirement ref: {requirement_ref}.\nCAP Submission Due Date: {due_date}.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        return [
            'ncr_ref' => $this->finding->reference ?: $this->finding->finding_number,
            'level' => $this->finding->level,
            'audit_title' => $this->finding->audit?->title ?? 'N/A',
            'requirement_ref' => $this->finding->requirement_ref ?? 'N/A',
            'due_date' => $this->finding->cap_due_at?->format('M d, Y') ?? 'N/A',
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        return route('ncrs.show', $this->finding->id);
    }
}
