<?php

namespace App\Notifications;

use App\Models\CorrectiveActionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CapSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public CorrectiveActionPlan $cap
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ncr = $this->cap->ncr;

        return (new MailMessage)
            ->subject("Corrective Action Plan Submitted: {$ncr?->reference}")
            ->line("A Corrective Action Plan (CAP) has been submitted for {$ncr?->reference}.")
            ->line("Root Cause: {$this->cap->root_cause}")
            ->line("Proposed Action: {$this->cap->description}")
            ->action('Review CAP Submission', route('ncrs.show', $ncr?->id))
            ->line('Please review the containment actions and record your approval/rejection.');
    }

    public function toArray(object $notifiable): array
    {
        $ncr = $this->cap->ncr;

        return [
            'cap_id' => $this->cap->id,
            'ncr_id' => $ncr?->id,
            'reference' => $ncr?->reference,
            'message' => "CAP submitted for NCR {$ncr?->reference} by {$this->cap->submitter?->name}.",
            'url' => route('ncrs.show', $ncr?->id),
            'type' => 'cap_submitted',
        ];
    }
}
