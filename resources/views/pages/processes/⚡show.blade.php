<?php

use App\Models\Audit;
use App\Models\Process;
use App\Models\User;
use App\Notifications\AuditAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Process Overview')] class extends Component {
    #[Locked]
    public ?Process $process = null;

    // Schedule Audit Modal state
    public bool $showScheduleModal = false;
    public string $auditTitle = '';
    public string $scheduledFor = '';
    public ?string $reviewerId = '';
    public array $assignedAuditorIds = [];

    public function mount($process)
    {
        $this->process = $process instanceof Process
            ? $process->loadMissing([
                'directorate',
                'department',
                'unit',
                'creator',
                'documents',
                'questions',
            ])
            : Process::with([
                'directorate',
                'department',
                'unit',
                'creator',
                'documents',
                'questions',
            ])->findOrFail($process);

        $this->authorize('view', $this->process);

        $defaultScheduled = $this->process->type === 'unplanned'
            ? now()->format('Y-m-d')
            : ($this->process->next_due_at ? $this->process->next_due_at->format('Y-m-d') : now()->format('Y-m-d'));

        $this->scheduledFor = $defaultScheduled;
        $this->auditTitle = $this->process->name . ' — ' . Carbon::parse($defaultScheduled)->format('F Y');
    }

    public function openScheduleModal(bool $startNow = false)
    {
        $date = $startNow ? now()->format('Y-m-d') : ($this->process->next_due_at ? $this->process->next_due_at->format('Y-m-d') : now()->format('Y-m-d'));
        $this->scheduledFor = $date;
        $this->auditTitle = $this->process->name . ' — ' . Carbon::parse($date)->format('F Y');
        $this->reviewerId = '';
        $this->assignedAuditorIds = [];
        $this->showScheduleModal = true;
    }

    public function scheduleAudit()
    {
        $this->validate([
            'auditTitle' => 'required|string|max:255',
            'scheduledFor' => 'required|date',
            'reviewerId' => 'nullable|exists:users,id',
            'assignedAuditorIds' => 'nullable|array',
            'assignedAuditorIds.*' => 'exists:users,id',
        ]);

        $scheduledDate = Carbon::parse($this->scheduledFor);
        $status = ($this->process->type === 'unplanned' && $scheduledDate->isToday()) ? 'in_progress' : 'scheduled';
        $refNumber = \App\Services\ReferenceGenerator::generateAuditReference($this->process->directorate?->code);

        // Snapshot questions
        $questionsSnapshot = $this->process->questions->map(function ($q) {
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

        $audit = Audit::create([
            'reference_number' => $refNumber,
            'process_id' => $this->process->id,
            'checklist_id' => $this->process->id,
            'title' => $this->auditTitle,
            'scheduled_for' => $scheduledDate,
            'type' => $this->process->type,
            'directorate_id' => $this->process->directorate_id,
            'lead_auditor_id' => auth()->id() ?? $this->process->created_by,
            'reviewer_id' => $this->reviewerId ?: null,
            'created_by' => auth()->id() ?? $this->process->created_by,
            'location' => 'Designated Station',
            'start_date' => $scheduledDate,
            'status' => $status,
            'questions_snapshot' => $questionsSnapshot,
        ]);

        if (! empty($this->assignedAuditorIds)) {
            $audit->auditors()->sync($this->assignedAuditorIds);

            // Notify each assigned field auditor
            foreach ($audit->auditors as $auditor) {
                $auditor->notify(new AuditAssigned($audit));
            }
        }

        // Notify reviewer
        if ($audit->reviewer) {
            $audit->reviewer->notify(new AuditAssigned($audit));
        }

        $this->showScheduleModal = false;

        return redirect()->route('audits.show', $audit->id)
            ->with('message', 'Audit "' . $audit->title . '" scheduled successfully.');
    }

    public function with()
    {
        return [
            'process' => $this->process,
            'reviewers' => User::role('reviewer')->orderBy('name')->get(),
            'fieldAuditors' => User::role('field_auditor')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('processes.index') }}" class="hover:underline" wire:navigate>Processes</a>
                <span>/</span>
                <span>Template Detail</span>
            </div>
            <div class="flex items-center space-x-3">
                <flux:heading size="xl">{{ $process->name }}</flux:heading>
                <flux:badge size="sm" :color="$process->status === 'active' ? 'emerald' : 'zinc'">
                    {{ ucfirst($process->status) }}
                </flux:badge>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <flux:button href="{{ route('processes.index') }}" variant="ghost" wire:navigate>
                &larr; Back to List
            </flux:button>
            <flux:button href="{{ route('processes.edit', $process->id) }}" variant="ghost" icon="pencil-square" wire:navigate>
                Edit
            </flux:button>

            @if($process->type === 'unplanned')
                <flux:button wire:click="openScheduleModal(true)" variant="primary" icon="play">
                    Start Now
                </flux:button>
            @else
                <flux:button wire:click="openScheduleModal(false)" variant="primary" icon="calendar-days">
                    Schedule Audit
                </flux:button>
            @endif
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Metadata Card -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <div>
            <span class="text-xs font-semibold text-zinc-500 uppercase">Organizational Scope</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-1">
                {{ $process->directorate->code ?? 'NCAA Authority-Wide' }}
            </div>
            <div class="text-xs text-zinc-500">
                {{ $process->department->name ?? ($process->unit->name ?? 'All Units') }}
            </div>
        </div>

        <div>
            <span class="text-xs font-semibold text-zinc-500 uppercase">Audit Type & Cadence</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-1">
                {{ ucfirst($process->type) }}
            </div>
            <div class="text-xs text-zinc-500">
                {{ $process->frequency ? ucfirst($process->frequency) . ' execution' : 'Surveillance / On Demand' }}
            </div>
        </div>

        <div>
            <span class="text-xs font-semibold text-zinc-500 uppercase">Next Scheduled Due</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-1 font-mono">
                {{ $process->next_due_at ? $process->next_due_at->format('M d, Y') : '—' }}
            </div>
            <div class="text-xs text-zinc-500">
                Created by {{ $process->creator->name ?? 'System' }}
            </div>
        </div>

        <div>
            <span class="text-xs font-semibold text-zinc-500 uppercase">Metrics</span>
            <div class="font-bold text-emerald-600 dark:text-emerald-400 text-sm mt-1">
                {{ $process->questions->count() }} Questions
            </div>
            <div class="text-xs text-zinc-500">
                {{ $process->documents->count() }} Reference Document(s)
            </div>
        </div>

        @if($process->description)
            <div class="col-span-full pt-3 border-t border-zinc-100 dark:border-zinc-700/60 text-xs text-zinc-600 dark:text-zinc-400">
                <strong class="text-zinc-700 dark:text-zinc-300">Description & Mandate:</strong>
                <p class="mt-0.5">{{ $process->description }}</p>
            </div>
        @endif
    </div>

    <!-- Attached Documents Section -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-3">
        <flux:heading size="md">Linked Regulatory Documents ({{ $process->documents->count() }})</flux:heading>

        @if($process->documents->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($process->documents as $doc)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                        <div class="truncate mr-2">
                            <div class="font-semibold text-xs text-zinc-900 dark:text-zinc-100 truncate">{{ $doc->title }}</div>
                            <div class="text-[11px] text-zinc-500 truncate">{{ $doc->original_name }}</div>
                        </div>
                        <flux:button href="{{ route('admin.documents.download', $doc->id) }}" size="xs" variant="ghost" icon="arrow-down-tray">
                            Download
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-xs text-zinc-500 italic">No reference documents linked to this process template.</div>
        @endif
    </div>

    <!-- Checklist Questions Ordered -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="md">Audit Checklist Questionnaire ({{ $process->questions->count() }})</flux:heading>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($process->questions as $q)
                <div class="py-3.5 space-y-2 first:pt-0 last:pb-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start space-x-2">
                            <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                #{{ $q->order }}
                            </span>
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $q->question }}</h4>
                                @if($q->requirement_ref)
                                    <span class="inline-block mt-1 text-[11px] font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-1.5 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                                        {{ $q->requirement_ref }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 shrink-0">
                            <flux:badge size="sm" color="zinc">{{ ucfirst($q->type) }}</flux:badge>
                            @if($q->source === 'ai')
                                <flux:badge size="sm" color="amber">AI</flux:badge>
                            @endif
                        </div>
                    </div>

                    @if($q->guidance)
                        <div class="text-xs text-zinc-500 italic pl-7">
                            <strong>Guidance:</strong> {{ $q->guidance }}
                        </div>
                    @endif

                    @if(in_array($q->type, ['radio', 'checkbox']) && !empty($q->options))
                        <div class="flex flex-wrap gap-1.5 pl-7 pt-1">
                            @foreach($q->options as $opt)
                                <span class="px-2 py-0.5 rounded text-[11px] bg-zinc-100 dark:bg-zinc-700/60 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-750">
                                    {{ $opt }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-6 text-center text-xs text-zinc-500">
                    No questions have been added to this process yet.
                </div>
            @endforelse
        </div>
    </div>

    <!-- SCHEDULE AUDIT MODAL -->
    <flux:modal wire:model="showScheduleModal" class="md:w-[32rem] space-y-4">
        <div>
            <flux:heading size="lg">
                {{ $process->type === 'unplanned' ? 'Start Unplanned Audit' : 'Schedule Audit Run' }}
            </flux:heading>
            <flux:subheading>
                Launch an execution run of "{{ $process->name }}". Questions will be snapshotted.
            </flux:subheading>
        </div>

        <form wire:submit.prevent="scheduleAudit" class="space-y-4">
            <flux:input wire:model="auditTitle" label="Audit Title *" required />

            <flux:input wire:model="scheduledFor" type="date" label="Scheduled Date *" required />

            <flux:select wire:model="reviewerId" label="Assign Quality Reviewer">
                <flux:select.option value="">No reviewer selected</flux:select.option>
                @foreach($reviewers as $rev)
                    <flux:select.option value="{{ $rev->id }}">{{ $rev->name }} ({{ $rev->email }})</flux:select.option>
                @endforeach
            </flux:select>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Assign Field Auditors (Multi-select)
                </label>
                <div class="max-h-36 overflow-y-auto space-y-1.5 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50">
                    @forelse($fieldAuditors as $auditor)
                        <label class="flex items-center space-x-2 text-xs text-zinc-800 dark:text-zinc-200 cursor-pointer">
                            <input type="checkbox" wire:model="assignedAuditorIds" value="{{ $auditor->id }}" class="rounded text-emerald-600 focus:ring-emerald-500">
                            <span>{{ $auditor->name }} &bull; <span class="text-zinc-500">{{ $auditor->email }}</span></span>
                        </label>
                    @empty
                        <div class="text-xs text-zinc-500 p-2">No field auditors registered.</div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end space-x-2 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showScheduleModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $process->type === 'unplanned' ? 'Launch Audit' : 'Confirm Schedule' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
