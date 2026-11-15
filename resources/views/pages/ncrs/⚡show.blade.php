<?php

use App\Models\Attachment;
use App\Models\CorrectiveActionPlan;
use App\Models\Finding;
use App\Notifications\CapReviewed;
use App\Notifications\CapSubmitted;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('NCR & CAP Resolution Details')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public Finding $ncr;

    // CAP Submission form (for auditee)
    public string $rootCause = '';
    public string $proposedAction = '';
    public string $targetDate = '';
    public array $closureEvidence = [];

    // CAP Review form (for reviewer / lead auditor)
    public string $reviewerComment = '';

    public function mount($ncr)
    {
        $id = $ncr instanceof Finding ? $ncr->id : $ncr;
        $this->ncr = Finding::with([
            'audit.directorate',
            'audit.reviewer',
            'audit.leadAuditor',
            'unit',
            'raisedBy',
            'answer.attachments',
            'cap.attachments',
            'cap.submitter',
        ])->findOrFail($id);

        $this->authorize('view', $this->ncr);

        $this->targetDate = now()->addDays(7)->format('Y-m-d');
    }

    public function submitCap()
    {
        $this->authorize('submitCap', $this->ncr);

        $evidenceRules = setting('audit.uploads.evidence', ['allowed_types' => 'jpg,jpeg,png,webp,pdf,docx,doc', 'max_size_kb' => 10240]);
        $allowedEvidenceMimes = $evidenceRules['allowed_types'] ?? 'jpg,jpeg,png,webp,pdf,docx,doc';
        $maxEvidenceKb = $evidenceRules['max_size_kb'] ?? 10240;

        $this->validate([
            'rootCause' => 'required|string',
            'proposedAction' => 'required|string',
            'targetDate' => 'required|date',
            'closureEvidence.*' => "nullable|file|mimes:{$allowedEvidenceMimes}|max:{$maxEvidenceKb}",
        ]);

        $cap = CorrectiveActionPlan::updateOrCreate(
            ['ncr_id' => $this->ncr->id],
            [
                'finding_id' => $this->ncr->id,
                'description' => $this->proposedAction,
                'proposed_action' => $this->proposedAction,
                'root_cause' => $this->rootCause,
                'root_cause_analysis' => $this->rootCause,
                'target_date' => $this->targetDate,
                'target_completion_date' => $this->targetDate,
                'submitted_by' => auth()->id(),
                'responsible_person' => auth()->user()->name,
                'status' => 'submitted',
            ]
        );

        // Upload closure evidence
        foreach ($this->closureEvidence as $file) {
            $path = $file->store('evidence/cap', 'local');
            $cap->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->ncr->update(['status' => 'cap_submitted']);

        // Notify reviewer
        if ($this->ncr->audit?->reviewer) {
            $this->ncr->audit->reviewer->notify(new CapSubmitted($cap));
        }

        $this->reset(['closureEvidence']);
        $this->ncr->load('cap.attachments');
        session()->flash('message', 'Corrective Action Plan submitted successfully to the QA Reviewer.');
    }

    public function acceptCap()
    {
        $this->authorize('reviewCap', $this->ncr);

        $cap = $this->ncr->cap;
        if (! $cap) {
            return;
        }

        $cap->update([
            'status' => 'accepted',
            'reviewer_comment' => $this->reviewerComment ?: 'CAP accepted. Proceed with closure verification.',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->ncr->update(['status' => 'cap_accepted']);

        if ($cap->submitter) {
            $cap->submitter->notify(new CapReviewed($cap));
        }

        $this->ncr->refresh();
        session()->flash('message', 'Corrective Action Plan accepted. NCR can now be verified and closed.');
    }

    public function rejectCap()
    {
        $this->authorize('reviewCap', $this->ncr);

        $this->validate([
            'reviewerComment' => 'required|string',
        ]);

        $cap = $this->ncr->cap;
        if (! $cap) {
            return;
        }

        $cap->update([
            'status' => 'rejected',
            'reviewer_comment' => $this->reviewerComment,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->ncr->update(['status' => 'cap_rejected']);

        if ($cap->submitter) {
            $cap->submitter->notify(new CapReviewed($cap));
        }

        $this->ncr->refresh();
        session()->flash('message', 'CAP rejected. Reason sent to the auditee unit for resubmission.');
    }

    public function closeNcr()
    {
        $this->ncr->update(['status' => 'closed']);
        session()->flash('message', 'NCR successfully verified and formally closed.');
    }

    public function with()
    {
        $user = auth()->user();
        $canReview = $user->hasRole('reviewer') || $user->hasRole('lead_auditor') || $user->hasRole('system_admin');
        $canSubmitCap = $user->hasRole('auditee') || $user->hasRole('system_admin');

        return [
            'canReview' => $canReview,
            'canSubmitCap' => $canSubmitCap,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('ncrs.index') }}" class="hover:underline" wire:navigate>NCRs</a>
                <span>/</span>
                <span>{{ $ncr->reference ?: $ncr->finding_number }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <flux:heading size="xl">{{ $ncr->reference ?: $ncr->finding_number }}</flux:heading>
                <flux:badge size="sm" :color="$ncr->level == 1 ? 'rose' : 'amber'">
                    Level {{ $ncr->level }} Non-Conformance
                </flux:badge>
                <flux:badge size="sm" :color="$ncr->status === 'closed' ? 'zinc' : ($ncr->status === 'cap_accepted' ? 'emerald' : 'rose')">
                    {{ ucwords(str_replace('_', ' ', $ncr->status)) }}
                </flux:badge>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <flux:button href="{{ route('ncrs.index') }}" variant="ghost" wire:navigate>
                &larr; Back to NCRs
            </flux:button>
            @if($ncr->status === 'cap_accepted' && $canReview)
                <flux:button wire:click="closeNcr" variant="primary" icon="check-badge">
                    Close NCR
                </flux:button>
            @endif
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    <!-- NCR Parameters Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Details Card -->
        <div class="md:col-span-2 p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-4">
            <flux:heading size="md">Deficiency Overview & Regulatory Requirement</flux:heading>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="text-zinc-500 uppercase font-semibold">Audit Engagement:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $ncr->audit->title }}</div>
                    <div class="text-[11px] text-zinc-500 font-mono">{{ $ncr->audit->reference_number }}</div>
                </div>

                <div>
                    <span class="text-zinc-500 uppercase font-semibold">Assigned Unit:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $ncr->unit->name ?? 'Unassigned' }}</div>
                    <div class="text-[11px] text-zinc-500">{{ $ncr->unit->directorate->name ?? 'DSD' }}</div>
                </div>

                <div>
                    <span class="text-zinc-500 uppercase font-semibold">Regulatory Reference Clause:</span>
                    <div class="font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                        {{ $ncr->requirement_ref ?: $ncr->criteria }}
                    </div>
                </div>

                <div>
                    <span class="text-zinc-500 uppercase font-semibold">CAP Deadline:</span>
                    <div class="font-bold font-mono {{ $ncr->isOverdue() ? 'text-rose-600' : 'text-zinc-900 dark:text-zinc-100' }} mt-0.5">
                        {{ $ncr->cap_due_at ? $ncr->cap_due_at->format('M d, Y H:i') : '—' }}
                        @if($ncr->isOverdue())
                            <flux:badge size="xs" color="rose">Overdue</flux:badge>
                        @endif
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700/60 text-xs">
                <strong class="text-zinc-700 dark:text-zinc-300">Detailed Non-Conformance Description:</strong>
                <p class="text-zinc-800 dark:text-zinc-200 mt-1 whitespace-pre-line leading-relaxed">
                    {{ $ncr->description ?: $ncr->condition }}
                </p>
            </div>

            <!-- Field Verification Evidence attached to the question -->
            @if($ncr->answer && $ncr->answer->attachments->count() > 0)
                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-700/60 space-y-2">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Original Audit Evidence:</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($ncr->answer->attachments as $att)
                            <a href="{{ route('attachments.show', $att->id) }}" target="_blank" class="w-20 h-16 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden block">
                                @if(str_starts_with($att->mime_type, 'image/'))
                                    <img src="{{ route('attachments.show', $att->id) }}" alt="{{ $att->original_name ?? 'Evidence attachment' }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-[10px] bg-zinc-100 dark:bg-zinc-900 font-bold">📄 FILE</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Action / Status Trail Card -->
        <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-4">
            <flux:heading size="md">Resolution Trail</flux:heading>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-zinc-500 uppercase font-semibold text-[10px]">Raised By:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $ncr->raisedBy->name ?? 'QA Inspector' }}</div>
                    <div class="text-[11px] text-zinc-500">{{ $ncr->created_at->format('M d, Y H:i') }}</div>
                </div>

                @if($ncr->cap)
                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700">
                        <span class="text-zinc-500 uppercase font-semibold text-[10px]">CAP Submitted By:</span>
                        <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $ncr->cap->submitter->name ?? $ncr->cap->responsible_person }}</div>
                        <div class="text-[11px] text-zinc-500">Target Date: {{ $ncr->cap->target_date?->format('M d, Y') ?? $ncr->cap->target_completion_date?->format('M d, Y') }}</div>
                    </div>
                @endif

                @if($ncr->cap?->reviewed_at)
                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700">
                        <span class="text-zinc-500 uppercase font-semibold text-[10px]">Reviewed By:</span>
                        <div class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $ncr->cap->reviewer->name ?? 'Reviewer' }}</div>
                        <div class="text-[11px] text-zinc-500">{{ $ncr->cap->reviewed_at->format('M d, Y H:i') }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- CAP SECTION -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-5">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Corrective Action Plan (CAP)</flux:heading>
            @if($ncr->cap)
                <flux:badge size="sm" :color="$ncr->cap->status === 'accepted' ? 'emerald' : ($ncr->cap->status === 'rejected' ? 'rose' : 'cyan')">
                    CAP {{ ucfirst($ncr->cap->status) }}
                </flux:badge>
            @endif
        </div>

        @if($ncr->cap)
            <!-- Existing CAP View -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div>
                        <span class="text-zinc-500 font-semibold uppercase">Root Cause Analysis:</span>
                        <p class="text-zinc-800 dark:text-zinc-200 mt-1 leading-relaxed">{{ $ncr->cap->root_cause ?: $ncr->cap->root_cause_analysis }}</p>
                    </div>

                    <div>
                        <span class="text-zinc-500 font-semibold uppercase">Corrective / Containment Action:</span>
                        <p class="text-zinc-800 dark:text-zinc-200 mt-1 leading-relaxed">{{ $ncr->cap->description ?: $ncr->cap->proposed_action }}</p>
                    </div>

                    @if($ncr->cap->reviewer_comment)
                        <div class="col-span-full pt-3 border-t border-zinc-200 dark:border-zinc-700">
                            <span class="text-zinc-500 font-semibold uppercase">QA Reviewer Remark:</span>
                            <p class="text-zinc-800 dark:text-zinc-200 mt-1 font-medium">{{ $ncr->cap->reviewer_comment }}</p>
                        </div>
                    @endif
                </div>

                <!-- Closure Evidence Attachments -->
                @if($ncr->cap->attachments->count() > 0)
                    <div class="space-y-2">
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">CAP Closure Verification Evidence ({{ $ncr->cap->attachments->count() }}):</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach($ncr->cap->attachments as $att)
                                <a href="{{ route('attachments.show', $att->id) }}" target="_blank" class="w-24 h-20 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden block">
                                    @if(str_starts_with($att->mime_type, 'image/'))
                                        <img src="{{ route('attachments.show', $att->id) }}" alt="{{ $att->original_name ?? 'Evidence attachment' }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-[10px] bg-zinc-100 dark:bg-zinc-800 font-bold p-1 text-center">
                                            📄 {{ strtoupper(pathinfo($att->original_name, PATHINFO_EXTENSION)) }}
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Reviewer Acceptance / Rejection Controls -->
                @if($canReview && in_array($ncr->status, ['cap_submitted', 'open']))
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3">
                        <flux:heading size="sm">Review & Decision on CAP</flux:heading>
                        <flux:textarea wire:model="reviewerComment" label="Reviewer Comment / Instructions" placeholder="State reasons if rejecting or instructions for closure..." rows="2" />

                        <div class="flex space-x-2 justify-end">
                            <flux:button wire:click="rejectCap" variant="danger" icon="x-mark">
                                Reject CAP
                            </flux:button>
                            <flux:button wire:click="acceptCap" variant="primary" icon="check">
                                Accept CAP
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <!-- Auditee Submission Form -->
            @if($canSubmitCap)
                <form wire:submit.prevent="submitCap" class="space-y-4">
                    <flux:textarea wire:model="rootCause" label="Root Cause Analysis *" rows="3" placeholder="Identify fundamental procedural, training, or organizational failure..." required />

                    <flux:textarea wire:model="proposedAction" label="Proposed Corrective Action & Containment Measures *" rows="3" placeholder="Describe concrete action plan to rectify discrepancy and prevent recurrence..." required />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:input wire:model="targetDate" type="date" label="Target Completion Date *" required />

                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Upload Closure Evidence (Photos / Work Orders)
                            </label>
                            <input type="file" wire:model="closureEvidence" multiple class="text-xs text-zinc-600 dark:text-zinc-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="primary" icon="paper-airplane">
                            Submit CAP for QA Review
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="p-6 text-center text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                    Awaiting CAP submission by the responsible auditee unit.
                </div>
            @endif
        @endif
    </div>
</div>
