<?php

namespace App\Notifications;

use App\Models\Audit;
use Illuminate\Bus\Queueable;

class AuditAssigned extends BaseNotification
{
    use Queueable;

    public function __construct(
        public Audit $audit
    ) {}

    public function eventKey(): string
    {
        return 'audit_assigned';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'NCAA QA Audit Assignment: {audit_title}',
            'body' => "You have been assigned to audit {audit_title} (Ref: {audit_ref}) scheduled for {due_date}.\nPlease review the regulatory checklists and scope before on-site inspection.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        $dateStr = $this->audit->scheduled_for
            ? $this->audit->scheduled_for->format('M d, Y')
            : ($this->audit->start_date ? $this->audit->start_date->format('M d, Y') : 'Today');

        return [
            'audit_title' => $this->audit->title,
            'audit_ref' => $this->audit->reference_number,
            'due_date' => $dateStr,
            'lead_auditor' => $this->audit->leadAuditor?->name ?? 'N/A',
            'reviewer' => $this->audit->reviewer?->name ?? 'N/A',
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        $isFieldOnly = method_exists($notifiable, 'hasRole')
            && $notifiable->hasRole('field_auditor')
            && $notifiable->roles()->count() === 1;

        return $isFieldOnly
            ? route('field.audit', $this->audit->id)
            : route('audits.show', $this->audit->id);
    }
}
