<?php

namespace App\Notifications;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Bus\Queueable;

class AuditSubmitted extends BaseNotification
{
    use Queueable;

    public function __construct(
        public Audit $audit,
        public User $auditor
    ) {}

    public function eventKey(): string
    {
        return 'audit_submitted';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'Audit Completed & Submitted for Review: {audit_title}',
            'body' => "Audit {audit_title} ({audit_ref}) was completed in the field by {submitted_by} and is now awaiting quality review.\nQuestions evaluated: {questions_count}.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        return [
            'audit_title' => $this->audit->title,
            'audit_ref' => $this->audit->reference_number,
            'submitted_by' => $this->auditor->name,
            'questions_count' => $this->audit->answers()->count(),
            'due_date' => now()->format('M d, Y'),
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        return route('review.show', $this->audit->id);
    }
}
