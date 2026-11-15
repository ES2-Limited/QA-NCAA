<?php

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditApproval;
use App\Models\Finding;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\NcrRaised;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Audit Review & NCR Raising')] class extends Component {
    #[Locked]
    public Audit $audit;

    // Reviewer Comments per answer
    public array $reviewerComments = [];

    // Flag as NCR Modal state
    public bool $showNcrModal = false;
    public ?int $targetAnswerId = null;
    public int $ncrLevel = 2; // 1 or 2
    public string $ncrDescription = '';
    public string $ncrRequirementRef = '';
    public ?string $ncrAssignedUnitId = '';

    // Action modals
    public bool $showReturnModal = false;
    public string $returnComment = '';

    public function mount($audit)
    {
        $id = $audit instanceof Audit ? $audit->id : $audit;
        $this->audit = Audit::with([
            'process.unit',
            'directorate',
            'leadAuditor',
            'reviewer',
            'auditors',
            'answers.attachments',
            'findings.unit',
            'approvals.user',
        ])->findOrFail($id);

        // Access control: assigned reviewer only or system admin
        if ($this->audit->reviewer_id !== auth()->id() && ! auth()->user()->hasRole('system_admin')) {
            abort(403, 'Unauthorized. You are not the assigned reviewer for this audit.');
        }

        foreach ($this->audit->answers as $a) {
            $this->reviewerComments[$a->id] = '';
        }

        $this->ncrAssignedUnitId = (string) ($this->audit->process?->unit_id ?? '');
    }

    public function openNcrModal(int $answerId, string $defaultRef = '', string $defaultDesc = '')
    {
        $this->targetAnswerId = $answerId;
        $this->ncrLevel = 2;
        $this->ncrRequirementRef = $defaultRef;
        $this->ncrDescription = $defaultDesc ? "Observed non-compliance: {$defaultDesc}" : '';
        $this->ncrAssignedUnitId = (string) ($this->audit->process?->unit_id ?? '');
        $this->showNcrModal = true;
    }

    public function raiseNcr()
    {
        $this->validate([
            'ncrLevel' => 'required|in:1,2',
            'ncrDescription' => 'required|string',
            'ncrRequirementRef' => 'nullable|string',
            'ncrAssignedUnitId' => 'nullable|exists:units,id',
        ]);

        $capDue = \App\Services\ReferenceGenerator::getCapSubmissionDeadline($this->ncrLevel);
        $reference = \App\Services\ReferenceGenerator::generateNcrReference();

        $ncr = Finding::create([
            'audit_id' => $this->audit->id,
            'audit_answer_id' => $this->targetAnswerId,
            'reference' => $reference,
            'finding_number' => $reference,
            'title' => 'Non-Conformance ' . $reference,
            'level' => (int) $this->ncrLevel,
            'description' => $this->ncrDescription,
            'condition' => $this->ncrDescription,
            'requirement_ref' => $this->ncrRequirementRef,
            'criteria' => $this->ncrRequirementRef,
            'assigned_unit_id' => $this->ncrAssignedUnitId ?: null,
            'raised_by' => auth()->id(),
            'cap_due_at' => $capDue,
            'due_date' => $capDue->toDateString(),
            'status' => 'open',
        ]);

        // Notify auditee unit users if unit specified
        if ($ncr->assigned_unit_id) {
            $auditeeUsers = User::role('auditee')->where('unit_id', $ncr->assigned_unit_id)->get();
            foreach ($auditeeUsers as $u) {
                $u->notify(new NcrRaised($ncr));
            }
        }

        $this->showNcrModal = false;
        $this->audit->load('findings.unit');
        session()->flash('message', "Non-Conformance Report {$ncr->reference} (Level {$ncr->level}) raised successfully.");
    }

    public function forwardToHeadOfAudit()
    {
        $this->audit->update([
            'status' => 'reviewed',
        ]);

        AuditApproval::create([
            'audit_id' => $this->audit->id,
            'user_id' => auth()->id(),
            'role' => 'reviewer',
            'action' => 'reviewed',
            'comment' => 'Audit review completed and non-conformances assessed. Forwarded to Head of Audit.',
        ]);

        session()->flash('message', 'Audit forwarded to Head of Audit for executive review.');

        return redirect()->route('review.index');
    }

    public function returnToFieldAuditor()
    {
        $this->validate([
            'returnComment' => 'required|string',
        ]);

        $this->audit->update([
            'status' => 'returned',
        ]);

        AuditApproval::create([
            'audit_id' => $this->audit->id,
            'user_id' => auth()->id(),
            'role' => 'reviewer',
            'action' => 'returned',
            'comment' => $this->returnComment,
        ]);

        // Notify assigned field auditors
        foreach ($this->audit->auditors as $auditor) {
            $auditor->notify(new \App\Notifications\AuditAssigned($this->audit));
        }

        $this->showReturnModal = false;
        session()->flash('message', 'Audit returned to field auditor for clarifications or additional evidence.');

        return redirect()->route('review.index');
    }

    public function with()
    {
        $questions = ! empty($this->audit->questions_snapshot)
            ? $this->audit->questions_snapshot
            : ($this->audit->process ? $this->audit->process->questions->toArray() : []);

        return [
            'questions' => $questions,
            'units' => Unit::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('review.index') }}" class="hover:underline" wire:navigate>Review Portal</a>
                <span>/</span>
                <span>{{ $audit->reference_number }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <flux:heading size="xl">{{ $audit->title }}</flux:heading>
                <flux:badge size="sm" :color="$audit->status === 'submitted' ? 'cyan' : 'blue'">
                    {{ ucwords(str_replace('_', ' ', $audit->status)) }}
                </flux:badge>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <flux:button href="{{ route('review.index') }}" variant="ghost" wire:navigate>
                &larr; Back
            </flux:button>
            <flux:button wire:click="$set('showReturnModal', true)" variant="danger" icon="arrow-uturn-left">
                Return to Field Auditor
            </flux:button>
            <flux:button wire:click="forwardToHeadOfAudit" variant="primary" icon="paper-airplane">
                Forward to Head of Audit
            </flux:button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- Audit Summary Banner -->
    <div class="p-4 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Lead Auditor:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">{{ $audit->leadAuditor->name ?? '—' }}</div>
        </div>
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Directorate:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">{{ $audit->directorate->code ?? 'DSD' }} &bull; {{ $audit->location }}</div>
        </div>
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Submitted Date:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5 font-mono">{{ $audit->submitted_at ? $audit->submitted_at->format('M d, Y H:i') : 'In progress' }}</div>
        </div>
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Active NCRs Raised:</span>
            <div class="font-bold text-rose-600 dark:text-rose-400 text-sm mt-0.5">{{ $audit->findings->count() }} Non-Conformances</div>
        </div>
    </div>

    <!-- Answers Review List (All Answers in Order) -->
    <div class="space-y-4">
        <flux:heading size="lg">Field Auditor Submissions & Verification Evidence</flux:heading>

        @foreach($questions as $idx => $q)
            @php
                $answer = $audit->answers->firstWhere('question_key', $idx);
                $hasAnswer = $answer !== null;
                $ncrForAnswer = $hasAnswer ? $audit->findings->firstWhere('audit_answer_id', $answer->id) : null;
            @endphp
            <div class="p-4 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-3 shadow-sm {{ $ncrForAnswer ? 'border-rose-400 dark:border-rose-800' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                #{{ $idx + 1 }}
                            </span>
                            <span class="text-xs font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-1.5 py-0.5 rounded">
                                {{ $q['requirement_ref'] ?? 'Standard Ref' }}
                            </span>
                            @if($ncrForAnswer)
                                <flux:badge size="sm" color="rose">NCR {{ $ncrForAnswer->reference }} (Level {{ $ncrForAnswer->level }})</flux:badge>
                            @endif
                        </div>
                        <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $q['question'] }}</h3>
                    </div>

                    @if($hasAnswer && ! $ncrForAnswer)
                        @php
                            $nonCompliantValues = setting('audit.non_compliant_answers', ['Non-compliant', 'non_compliant']);
                            $isSuggestedNcr = is_string($answer->value) && in_array(strtolower($answer->value), array_map('strtolower', $nonCompliantValues));
                        @endphp
                        <flux:button 
                            wire:click="openNcrModal({{ $answer->id }}, '{{ addslashes($q['requirement_ref'] ?? '') }}', '{{ addslashes($q['question']) }}')" 
                            size="xs" 
                            variant="danger" 
                            icon="exclamation-triangle"
                            class="{{ $isSuggestedNcr ? 'ring-2 ring-rose-500 animate-pulse' : '' }}">
                            {{ $isSuggestedNcr ? 'Action Required: Raise NCR' : 'Flag as Non-Compliant (NCR)' }}
                        </flux:button>
                    @endif
                </div>

                <!-- Answer Content & Geo -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 p-3 bg-zinc-50 dark:bg-zinc-900/60 rounded-xl text-xs">
                    <div>
                        <span class="text-zinc-500 font-semibold uppercase">Recorded Value:</span>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">
                            @if($hasAnswer)
                                @if(is_array($answer->value))
                                    {{ implode(', ', $answer->value) }}
                                @else
                                    {{ $answer->value }}
                                @endif
                            @else
                                <span class="text-rose-500">Unanswered</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <span class="text-zinc-500 font-semibold uppercase">Auditor Remark:</span>
                        <div class="text-zinc-700 dark:text-zinc-300 mt-0.5">
                            {{ $answer?->comment ?: 'No remark attached.' }}
                        </div>
                    </div>

                    <div>
                        <span class="text-zinc-500 font-semibold uppercase">GPS Stamp & Time:</span>
                        <div class="text-zinc-700 dark:text-zinc-300 mt-0.5 font-mono">
                            @if($answer && $answer->lat && $answer->lng)
                                <a href="https://www.openstreetmap.org/?mlat={{ $answer->lat }}&mlon={{ $answer->lng }}#map=16/{{ $answer->lat }}/{{ $answer->lng }}" target="_blank" class="text-emerald-600 dark:text-emerald-400 font-bold hover:underline">
                                    📍 {{ round($answer->lat, 4) }}, {{ round($answer->lng, 4) }} (OpenMap)
                                </a>
                            @else
                                <span class="text-zinc-400">Location unavailable</span>
                            @endif
                            <div class="text-[11px] text-zinc-400">{{ $answer?->answered_at?->format('M d, H:i') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Evidence Thumbnails (Lightbox compatible) -->
                @if($hasAnswer && $answer->attachments->count() > 0)
                    <div class="space-y-1.5">
                        <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Attached Evidence ({{ $answer->attachments->count() }}):</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach($answer->attachments as $att)
                                <a href="{{ route('attachments.show', $att->id) }}" target="_blank" class="block group relative rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 w-20 h-16 bg-zinc-100 dark:bg-zinc-800">
                                    @if(str_starts_with($att->mime_type, 'image/'))
                                        <img src="{{ route('attachments.show', $att->id) }}" alt="Evidence" class="w-full h-full object-cover group-hover:scale-105 transition">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-[10px] text-zinc-600 font-bold p-1 text-center">
                                            📄 {{ strtoupper(pathinfo($att->original_name, PATHINFO_EXTENSION)) }}
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- RAISE NCR MODAL -->
    <flux:modal wire:model="showNcrModal" class="md:w-[32rem] space-y-4">
        <div>
            <flux:heading size="lg">Raise Non-Conformance Report (NCR)</flux:heading>
            <flux:subheading>Document a formal finding against statutory Nig.CARs or NCAA QA requirements</flux:subheading>
        </div>

        <form wire:submit.prevent="raiseNcr" class="space-y-4">
            <flux:select wire:model="ncrLevel" label="Non-Conformance Level *" required>
                <flux:select.option value="1">Level 1 (Immediate Safety Risk &bull; CAP due in 72 hours)</flux:select.option>
                <flux:select.option value="2">Level 2 (Standard Quality Deficiency &bull; CAP due in 14 days)</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="ncrDescription" label="Non-Conformance Description *" rows="3" placeholder="Describe specific condition observed..." required />

            <flux:input wire:model="ncrRequirementRef" label="Regulatory Clause / Standard Reference *" required />

            <flux:select wire:model="ncrAssignedUnitId" label="Assigned Responsible Unit">
                <flux:select.option value="">Select auditee unit</flux:select.option>
                @foreach($units as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end space-x-2 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showNcrModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="danger" icon="exclamation-circle">Raise NCR</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- RETURN TO AUDITOR MODAL -->
    <flux:modal wire:model="showReturnModal" class="md:w-96 space-y-4">
        <div>
            <flux:heading size="lg">Return Audit to Field Auditor</flux:heading>
            <flux:subheading>Re-open audit on /field for additional clarification or missing evidence</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:textarea wire:model="returnComment" label="Reviewer Clarification Comment *" rows="3" placeholder="Explain what evidence or correction is required..." required />

            <div class="flex justify-end space-x-2 pt-2">
                <flux:button wire:click="$set('showReturnModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="returnToFieldAuditor" variant="danger">Confirm Return</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
