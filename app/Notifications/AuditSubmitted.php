<?php

namespace App\Notifications;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuditSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public Audit $audit,
        public ?User $submittedBy = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $submitterName = $this->submittedBy ? $this->submittedBy->name : 'Field Auditor';

        return (new MailMessage)
            ->subject("NCAA QA Audit Submitted for Review: {$this->audit->title}")
            ->line("Audit {$this->audit->title} has been completed and submitted by {$submitterName}.")
            ->line('All field verification questions have been evaluated and recorded.')
            ->action('Review Audit Engagement', route('audits.show', $this->audit->id))
            ->line('Please inspect findings and submit your review recommendations.');
    }

    public function toArray(object $notifiable): array
    {
        $submitterName = $this->submittedBy ? $this->submittedBy->name : 'Field Auditor';

        return [
            'audit_id' => $this->audit->id,
            'title' => $this->audit->title,
            'submitted_by' => $submitterName,
            'message' => "Audit {$this->audit->title} has been submitted by {$submitterName} for review.",
            'url' => route('audits.show', $this->audit->id),
            'type' => 'audit_submitted',
        ];
    }
}
