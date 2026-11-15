<?php

namespace App\Notifications;

use App\Models\CorrectiveActionPlan;
use Illuminate\Bus\Queueable;

class CapReviewed extends BaseNotification
{
    use Queueable;

    public function __construct(
        public CorrectiveActionPlan $cap
    ) {}

    public function eventKey(): string
    {
        return 'cap_reviewed';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'CAP Decision: NCR {ncr_ref} Plan {status}',
            'body' => "Your Corrective Action Plan for NCR {ncr_ref} was {status} by the quality reviewer on {review_date}.\nReviewer notes: {comments}.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        return [
            'ncr_ref' => $this->cap->finding?->reference ?: $this->cap->finding?->finding_number ?? 'N/A',
            'status' => strtoupper($this->cap->status),
            'review_date' => $this->cap->reviewed_at?->format('M d, Y') ?? now()->format('M d, Y'),
            'comments' => $this->cap->review_notes ?: 'No additional notes provided.',
            'audit_title' => $this->cap->finding?->audit?->title ?? 'N/A',
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        return route('ncrs.show', $this->cap->finding_id);
    }
}
