<?php

namespace App\Notifications;

use App\Models\Process;
use Illuminate\Bus\Queueable;

class RoutineAuditDue extends BaseNotification
{
    use Queueable;

    public int $daysUntilDue;

    public function __construct(
        public Process $process,
        public int $daysRemaining
    ) {
        $this->daysUntilDue = $daysRemaining;
    }

    public function eventKey(): string
    {
        return 'routine_due';
    }

    public function defaultTemplate(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'subject' => 'Quality Routine Audit Due in {days_remaining} Days: {process_name}',
            'body' => "Recurring quality audit procedure {process_name} is due on {due_date} ({days_remaining} days remaining).\nPlease schedule the required field inspection engagement.",
        ];
    }

    public function placeholderData(object $notifiable): array
    {
        return [
            'process_name' => $this->process->name,
            'due_date' => $this->process->next_due_at?->format('M d, Y') ?? 'N/A',
            'days_remaining' => $this->daysRemaining,
        ];
    }

    public function targetUrl(object $notifiable): string
    {
        return route('processes.show', $this->process->id);
    }
}
