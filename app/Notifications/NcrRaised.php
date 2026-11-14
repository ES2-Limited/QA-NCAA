<?php

namespace App\Notifications;

use App\Models\Finding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NcrRaised extends Notification
{
    use Queueable;

    public function __construct(
        public Finding $ncr
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("NCAA Non-Conformance Report Raised: {$this->ncr->reference}")
            ->line("A Level {$this->ncr->level} Non-Conformance Report ({$this->ncr->reference}) has been raised for your unit.")
            ->line("Requirement Reference: {$this->ncr->requirement_ref}")
            ->line("Description: {$this->ncr->description}")
            ->line("CAP Due Date: {$this->ncr->cap_due_at?->format('M d, Y H:i')}")
            ->action('View NCR & Submit CAP', route('ncrs.show', $this->ncr->id))
            ->line('Mandatory CAP submission is required before the specified deadline.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ncr_id' => $this->ncr->id,
            'reference' => $this->ncr->reference,
            'level' => $this->ncr->level,
            'message' => "Non-Conformance Report {$this->ncr->reference} (Level {$this->ncr->level}) raised for your unit.",
            'url' => route('ncrs.show', $this->ncr->id),
            'type' => 'ncr_raised',
        ];
    }
}
