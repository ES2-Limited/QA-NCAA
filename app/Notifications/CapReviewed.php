<?php

namespace App\Notifications;

use App\Models\CorrectiveActionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CapReviewed extends Notification
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
        $statusText = strtoupper($this->cap->status);

        return (new MailMessage)
            ->subject("Corrective Action Plan Review: {$statusText} for {$ncr?->reference}")
            ->line("Your Corrective Action Plan for {$ncr?->reference} has been marked as {$statusText}.")
            ->line("Reviewer Remark: {$this->cap->reviewer_comment}")
            ->action('View NCR Details', route('ncrs.show', $ncr?->id));
    }

    public function toArray(object $notifiable): array
    {
        $ncr = $this->cap->ncr;

        return [
            'cap_id' => $this->cap->id,
            'ncr_id' => $ncr?->id,
            'reference' => $ncr?->reference,
            'status' => $this->cap->status,
            'message' => "CAP for NCR {$ncr?->reference} was {$this->cap->status} by reviewer.",
            'url' => route('ncrs.show', $ncr?->id),
            'type' => 'cap_reviewed',
        ];
    }
}
