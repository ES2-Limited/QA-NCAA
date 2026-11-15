<?php

namespace App\Notifications;

use App\Models\Audit;
use App\Models\WorkflowStep;
use Illuminate\Bus\Queueable;

class ApprovalNeeded extends BaseNotification
{
    use Queueable;

    public function __construct(
        public Audit $audit,
        public WorkflowStep $step
    ) {}

    public function eventKey(): string
    {
        return 'approval_needed';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'Approval Required: {step_name} for Audit {audit_title}',
            'body' => "Audit {audit_title} ({audit_ref}) has reached workflow stage \"{step_name}\" and is awaiting your review and authorization.\nDue Date: {due_date}.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        return [
            'audit_title' => $this->audit->title,
            'audit_ref' => $this->audit->reference_number,
            'step_name' => $this->step->name,
            'step_role' => $this->step->role,
            'due_date' => now()->addDays(3)->format('M d, Y'),
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        return route('approvals.show', $this->audit->id);
    }
}
