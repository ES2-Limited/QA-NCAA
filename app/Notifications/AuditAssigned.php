<?php

namespace App\Notifications;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuditAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public Audit $audit
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dateStr = $this->audit->scheduled_for
            ? $this->audit->scheduled_for->format('M d, Y')
            : ($this->audit->start_date ? $this->audit->start_date->format('M d, Y') : 'Today');

        return (new MailMessage)
            ->subject("NCAA QA Audit Assignment: {$this->audit->title}")
            ->line("You've been added to audit {$this->audit->title} scheduled for {$dateStr}.")
            ->action('View Audit Engagement', route('audits.show', $this->audit->id))
            ->line('Please review the audit scope and prepare the required regulatory checklists.');
    }

    public function toArray(object $notifiable): array
    {
        $dateStr = $this->audit->scheduled_for
            ? $this->audit->scheduled_for->format('M d, Y')
            : ($this->audit->start_date ? $this->audit->start_date->format('M d, Y') : 'Today');

        $isFieldOnly = method_exists($notifiable, 'hasRole')
            && $notifiable->hasRole('field_auditor')
            && $notifiable->roles()->count() === 1;

        $targetUrl = $isFieldOnly
            ? route('field.audit', $this->audit->id)
            : route('audits.show', $this->audit->id);

        return [
            'audit_id' => $this->audit->id,
            'title' => $this->audit->title,
            'scheduled_for' => $dateStr,
            'message' => "You've been added to audit {$this->audit->title} scheduled for {$dateStr}.",
            'url' => $targetUrl,
            'type' => 'audit_assigned',
        ];
    }
}
