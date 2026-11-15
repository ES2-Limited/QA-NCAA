<?php

namespace App\Notifications;

use App\Models\CorrectiveActionPlan;
use Illuminate\Bus\Queueable;

class CapSubmitted extends BaseNotification
{
    use Queueable;

    public function __construct(
        public CorrectiveActionPlan $cap
    ) {}

    public function eventKey(): string
    {
        return 'cap_submitted';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'Corrective Action Plan Submitted for NCR {ncr_ref}',
            'body' => "A Corrective Action Plan for NCR {ncr_ref} was submitted by {submitted_by} on {submission_date}.\nPlease review the root cause analysis and proposed containment measures.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        return [
            'ncr_ref' => $this->cap->finding?->reference ?: $this->cap->finding?->finding_number ?? 'N/A',
            'submitted_by' => $this->cap->submitter?->name ?? 'Auditee',
            'submission_date' => $this->cap->submitted_at?->format('M d, Y') ?? now()->format('M d, Y'),
            'audit_title' => $this->cap->finding?->audit?->title ?? 'N/A',
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        return route('ncrs.show', $this->cap->finding_id);
    }
}
