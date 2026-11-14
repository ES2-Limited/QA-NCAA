<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\Process;
use App\Models\User;
use App\Notifications\AuditAssigned;
use App\Notifications\RoutineAuditDue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RoutineAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audits:routine-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 14/7-day advance alerts for routine audits and auto-create overdue routine audit instances.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $this->info("Running routine audit alerts for {$today->toDateString()}...");

        $processes = Process::where('type', 'routine')
            ->where('status', 'active')
            ->whereNotNull('next_due_at')
            ->with(['creator', 'questions'])
            ->get();

        foreach ($processes as $process) {
            $nextDue = Carbon::parse($process->next_due_at)->startOfDay();
            $daysDiff = (int) round($today->diffInDays($nextDue, false));

            // 1. Advance notification (exactly 14 or 7 days away)
            if ($daysDiff === 14 || $daysDiff === 7) {
                $recipient = $process->creator ?: User::role('lead_auditor')->first();
                if ($recipient) {
                    // Safe to run twice on same day: check if already alerted today
                    $alreadyAlerted = $recipient->notifications()
                        ->where('type', RoutineAuditDue::class)
                        ->whereDate('created_at', $today)
                        ->where('data->process_id', $process->id)
                        ->where('data->days_until_due', $daysDiff)
                        ->exists();

                    if (! $alreadyAlerted) {
                        $recipient->notify(new RoutineAuditDue($process, $daysDiff));
                        $this->info("Sent {$daysDiff}-day alert for process [{$process->name}] to {$recipient->email}");
                    }
                }
            }

            // 2. Due today or in the past: auto-create next Audit and roll next_due_at forward
            if ($daysDiff <= 0) {
                // Check if an auto-created routine audit for this due date already exists
                $alreadyCreatedForDueDate = Audit::where('process_id', $process->id)
                    ->whereDate('scheduled_for', $nextDue)
                    ->exists();

                if (! $alreadyCreatedForDueDate) {
                    // Check last audit of this process to reuse team assignments if present
                    $lastAudit = Audit::where('process_id', $process->id)
                        ->orderByDesc('id')
                        ->with(['auditors'])
                        ->first();

                    $leadAuditorId = $lastAudit?->lead_auditor_id ?? $process->created_by ?? User::role('lead_auditor')->value('id');
                    $reviewerId = $lastAudit?->reviewer_id;
                    $assignedAuditorIds = $lastAudit ? $lastAudit->auditors->pluck('id')->toArray() : [];

                    // Snapshot questions
                    $questionsSnapshot = $process->questions->map(function ($q) {
                        return [
                            'id' => $q->id,
                            'order' => $q->order,
                            'question' => $q->question,
                            'type' => $q->type,
                            'options' => $q->options,
                            'requirement_ref' => $q->requirement_ref,
                            'guidance' => $q->guidance,
                            'source' => $q->source,
                        ];
                    })->toArray();

                    $scheduledFor = $nextDue->copy();
                    $refNumber = 'AUD-'.strtoupper(Str::random(3)).'-'.now()->format('YmdHis');

                    $audit = Audit::create([
                        'reference_number' => $refNumber,
                        'process_id' => $process->id,
                        'checklist_id' => $process->id,
                        'title' => $process->name.' — '.$scheduledFor->format('F Y'),
                        'scheduled_for' => $scheduledFor,
                        'type' => 'routine',
                        'directorate_id' => $process->directorate_id,
                        'lead_auditor_id' => $leadAuditorId,
                        'reviewer_id' => $reviewerId,
                        'created_by' => $process->created_by,
                        'location' => 'Scheduled Station',
                        'start_date' => $scheduledFor,
                        'status' => 'scheduled',
                        'questions_snapshot' => $questionsSnapshot,
                    ]);

                    if (! empty($assignedAuditorIds)) {
                        $audit->auditors()->sync($assignedAuditorIds);

                        // Notify assigned team
                        foreach ($audit->auditors as $auditor) {
                            $auditor->notify(new AuditAssigned($audit));
                        }
                    }

                    if ($audit->reviewer) {
                        $audit->reviewer->notify(new AuditAssigned($audit));
                    }

                    $this->info("Auto-created audit [{$audit->title}] (Ref: {$audit->reference_number})");
                }

                // Roll next_due_at forward by its frequency
                $rolledDate = match ($process->frequency) {
                    'weekly' => $nextDue->copy()->addWeek(),
                    'monthly' => $nextDue->copy()->addMonth(),
                    'quarterly' => $nextDue->copy()->addMonths(3),
                    'yearly' => $nextDue->copy()->addYear(),
                    default => $nextDue->copy()->addMonth(),
                };

                // If rolled date is still in the past, push it past today
                while ($rolledDate->isPast()) {
                    $rolledDate = match ($process->frequency) {
                        'weekly' => $rolledDate->addWeek(),
                        'monthly' => $rolledDate->addMonth(),
                        'quarterly' => $rolledDate->addMonths(3),
                        'yearly' => $rolledDate->addYear(),
                        default => $rolledDate->addMonth(),
                    };
                }

                $process->update([
                    'next_due_at' => $rolledDate,
                ]);

                $this->info("Rolled next_due_at for [{$process->name}] to {$rolledDate->toDateString()}");
            }
        }

        return self::SUCCESS;
    }
}
