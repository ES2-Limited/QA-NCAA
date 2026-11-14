<?php

namespace App\Notifications;

use App\Models\Process;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoutineAuditDue extends Notification
{
    use Queueable;

    public function __construct(
        public Process $process,
        public int $daysUntilDue
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueStr = $this->process->next_due_at ? $this->process->next_due_at->format('M d, Y') : 'soon';

        return (new MailMessage)
            ->subject("Routine Audit Due in {$this->daysUntilDue} Days: {$this->process->name}")
            ->line("The routine audit for \"{$this->process->name}\" is due on {$dueStr} (in {$this->daysUntilDue} days).")
            ->action('View Process Template', route('processes.show', $this->process->id))
            ->line('Please ensure auditor teams and documentation are prepared before the scheduled audit date.');
    }

    public function toArray(object $notifiable): array
    {
        $dueStr = $this->process->next_due_at ? $this->process->next_due_at->format('M d, Y') : 'soon';

        return [
            'process_id' => $this->process->id,
            'process_name' => $this->process->name,
            'days_until_due' => $this->daysUntilDue,
            'due_date' => $dueStr,
            'message' => "Routine audit for \"{$this->process->name}\" is due in {$this->daysUntilDue} days ({$dueStr}).",
            'url' => route('processes.show', $this->process->id),
            'type' => 'routine_audit_due',
        ];
    }
}
