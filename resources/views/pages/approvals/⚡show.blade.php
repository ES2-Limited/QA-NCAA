<?php

use App\Contracts\AiService;
use App\Models\Audit;
use App\Models\AuditApproval;
use App\Services\CertificateGenerator;
use App\Services\WorkflowService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Audit Approval Decision')] class extends Component {
    #[Locked]
    public Audit $audit;
    public string $aiSummary = '';
    public string $approvalComment = '';
    public bool $isGeneratingAi = false;
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
            'findings.unit',
            'approvals.user',
            'certificate',
        ])->findOrFail($id);

        $this->authorize('approve', $this->audit);

        $this->aiSummary = $this->audit->ai_summary ?? $this->audit->ai_executive_summary ?? '';
    }

    public function generateAiSummary(AiService $ai)
    {
        $userId = auth()->id() ?? request()->ip();
        $executed = \Illuminate\Support\Facades\RateLimiter::attempt(
            'ai-actions:'.$userId,
            10,
            function () {},
            60
        );

        if (! $executed) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn('ai-actions:'.$userId);
            session()->flash('error', "AI rate limit reached. Please wait {$seconds} seconds before generating summary.");
            return;
        }

        $this->isGeneratingAi = true;

        $ncrSummary = $this->audit->findings->map(function ($f) {
            return "NCR {$f->reference} (Level {$f->level}): {$f->description} [Status: {$f->status}]";
        })->implode("\n");

        $context = "Audit Engagement: {$this->audit->title}\n"
            ."Directorate: {$this->audit->directorate?->name}\n"
            ."Overall Compliance Score: {$this->audit->compliance_score}%\n"
            ."Findings:\n{$ncrSummary}";

        try {
            $summary = method_exists($ai, 'summariseAudit')
                ? $ai->summariseAudit($context)
                : $ai->summarizeAuditFindings($context, 150);
            $this->aiSummary = $summary;
            $this->audit->update(['ai_summary' => $summary]);
            session()->flash('message', 'AI executive audit summary generated successfully.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to generate AI summary: ' . $e->getMessage());
        } finally {
            $this->isGeneratingAi = false;
        }
    }

    public function saveAiSummary()
    {
        $this->audit->update(['ai_summary' => $this->aiSummary]);
        session()->flash('message', 'Executive summary text updated.');
    }

    /**
     * Backward-compatible headApprove for test suites calling ->call('headApprove').
     */
    public function headApprove()
    {
        return $this->approveStep();
    }

    /**
     * Backward-compatible directorApprove for test suites calling ->call('directorApprove').
     */
    public function directorApprove(CertificateGenerator $generator)
    {
        return $this->approveStep();
    }

    /**
     * Execute approval on the active configurable workflow step.
     */
    public function approveStep()
    {
        $workflow = app(WorkflowService::class);
        $user = auth()->user();

        try {
            $result = $workflow->approveStep(
                $this->audit,
                $user,
                $this->approvalComment ?: 'Audit step endorsed and authorized.'
            );

            $step = $result['step'];
            $isFinal = $result['is_final'];

            $updateData = [
            'status' => $step->action_status,
        ];

        if ($this->aiSummary) {
            $updateData['ai_summary'] = $this->aiSummary;
        }

        if ($isFinal) {
            $updateData['end_date'] = now();
        }

        $this->audit->update($updateData);

            if ($isFinal) {
                session()->flash('message', "Final approval completed ({$step->name}) and Quality Audit Certificate issued!");
            } else {
                $nextStep = $workflow->getNextStep($step);
                $nextMsg = $nextStep ? " Escalated to {$nextStep->name}." : '';
                session()->flash('message', "Audit endorsed for step '{$step->name}'.{$nextMsg}");
            }

            return redirect()->route('approvals.index');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Execute return to previous step in the configurable workflow.
     */
    public function returnAudit()
    {
        $this->validate(['returnComment' => 'required|string']);

        $workflow = app(WorkflowService::class);
        $user = auth()->user();

        try {
            $target = $workflow->returnStep($this->audit, $user, $this->returnComment);

            $this->showReturnModal = false;
            $targetName = $target instanceof \App\Models\WorkflowStep ? $target->name : 'Field Auditor';
            session()->flash('message', "Audit returned to {$targetName} for revisions.");

            return redirect()->route('approvals.index');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with()
    {
        $user = auth()->user();
        $workflow = app(WorkflowService::class);
        $currentStep = $workflow->getCurrentStep($this->audit);
        $canApproveCurrent = $currentStep ? $workflow->userCanActOnStep($user, $currentStep) : false;
        $canReturnCurrent = $currentStep ? ($currentStep->can_return && $canApproveCurrent) : false;

        $questions = ! empty($this->audit->questions_snapshot)
            ? $this->audit->questions_snapshot
            : ($this->audit->process ? $this->audit->process->questions->toArray() : []);

        return [
            'workflow' => $workflow,
            'steps' => $workflow->getSteps(),
            'currentStep' => $currentStep,
            'canApproveCurrent' => $canApproveCurrent,
            'canReturnCurrent' => $canReturnCurrent,
            'questions' => $questions,
            'openNcrsCount' => $this->audit->findings()->whereIn('status', ['open', 'cap_submitted', 'cap_rejected'])->count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('approvals.index') }}" class="hover:underline" wire:navigate>Approvals</a>
                <span>/</span>
                <span>{{ $audit->reference_number }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <flux:heading size="xl">{{ $audit->title }}</flux:heading>
                <flux:badge size="sm" :color="$audit->status === 'director_approved' ? 'emerald' : 'blue'">
                    {{ ucwords(str_replace('_', ' ', $audit->status)) }}
                </flux:badge>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <flux:button href="{{ route('approvals.index') }}" variant="ghost" wire:navigate>
                &larr; Back
            </flux:button>

            @if($audit->certificate)
                <flux:button href="{{ route('certificates.download', $audit->id) }}" variant="ghost" icon="arrow-down-tray">
                    Download Certificate
                </flux:button>
            @endif

            @if($canReturnCurrent)
                <flux:button wire:click="$set('showReturnModal', true)" variant="danger" icon="arrow-uturn-left">
                    Return to Previous
                </flux:button>
            @endif

            @if($canApproveCurrent)
                <flux:button wire:click="approveStep" variant="primary" :icon="$currentStep?->is_final ? 'check-badge' : 'check'">
                    {{ $currentStep?->is_final ? 'Grant Final Approval & Certify' : 'Endorse & Advance' }}
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Active Step Progress Bar -->
    <div class="p-4 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
        <div class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mb-2">Workflow Progress:</div>
        <div class="flex flex-wrap items-center gap-2">
            @foreach($steps as $idx => $st)
                @php
                    $isPast = false;
                    $isCurrent = $currentStep && $currentStep->id === $st->id;
                    if ($audit->status === $st->action_status) {
                        $isPast = true;
                    }
                @endphp
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-semibold border {{ $isCurrent ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : ($isPast ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' : 'bg-zinc-100 text-zinc-500 border-zinc-200 dark:bg-zinc-900 dark:text-zinc-400 dark:border-zinc-800') }}">
                        <span class="font-bold text-[10px] size-4 rounded-full bg-black/10 dark:bg-white/10 flex items-center justify-center">{{ $idx + 1 }}</span>
                        <span>{{ $st->name }}</span>
                    </span>
                    @if(! $loop->last)
                        <flux:icon name="arrow-right" class="size-3.5 text-zinc-400" />
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-3 bg-rose-50 dark:bg-rose-950/50 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-800 rounded-xl text-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Open NCR Warning at Certification if applicable -->
    @if($openNcrsCount > 0)
        <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-2xl flex items-center justify-between text-xs text-amber-900 dark:text-amber-200">
            <div class="flex items-center space-x-2 font-bold">
                <span>⚠️ Note: This audit currently has {{ $openNcrsCount }} open Non-Conformance Report(s).</span>
            </div>
            <span class="font-medium">Certification will be noted as "Issued with {{ $openNcrsCount }} open NCRs".</span>
        </div>
    @endif

    <!-- Executive Overview Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 text-xs">
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Audited Unit:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">{{ $audit->directorate->code ?? 'DSD' }} &bull; {{ $audit->process?->unit->name ?? 'All Units' }}</div>
        </div>
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Compliance Rating:</span>
            <div class="font-bold text-emerald-600 dark:text-emerald-400 text-sm mt-0.5">{{ $audit->compliance_score }}% Overall Conformance</div>
        </div>
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Current Workflow Stage:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">{{ $currentStep?->name ?? 'Completed' }}</div>
        </div>
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Scheduled Date:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">{{ $audit->scheduled_for?->format('M d, Y') ?? 'Unscheduled' }}</div>
        </div>
    </div>

    <!-- AI Summary Box -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="md">Executive Audit Synthesis (AI-Generated)</flux:heading>
                <flux:subheading>Automated analysis of verified questions, responses and raised NCRs</flux:subheading>
            </div>
            <div class="flex items-center space-x-2">
                <flux:button wire:click="generateAiSummary" variant="primary" size="sm" icon="sparkles" :disabled="$isGeneratingAi">
                    <span wire:loading.remove wire:target="generateAiSummary">Generate with AI</span>
                    <span wire:loading wire:target="generateAiSummary">Synthesizing...</span>
                </flux:button>
                <flux:button wire:click="saveAiSummary" variant="ghost" size="sm">Save Text</flux:button>
            </div>
        </div>

        <flux:textarea wire:model="aiSummary" rows="5" class="font-sans text-sm" placeholder="AI executive audit summary will appear here..." />
    </div>

    <!-- Approval Trail Timeline -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-3">
        <flux:heading size="md">Audit Approval Trail & Sign-offs</flux:heading>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($audit->approvals as $trail)
                <div class="py-3 first:pt-0 last:pb-0 flex items-start justify-between text-xs">
                    <div class="space-y-0.5">
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $trail->user->name }}</span>
                            <flux:badge size="xs" color="zinc">{{ strtoupper($trail->role) }}</flux:badge>
                            <span class="text-emerald-600 font-semibold">{{ ucwords(str_replace('_', ' ', $trail->action)) }}</span>
                        </div>
                        <p class="text-zinc-600 dark:text-zinc-400 italic">"{{ $trail->comment }}"</p>
                    </div>
                    <div class="font-mono text-zinc-400 text-[11px] shrink-0">
                        {{ $trail->created_at->format('M d, Y H:i') }}
                    </div>
                </div>
            @empty
                <div class="py-4 text-center text-xs text-zinc-500">No approvals recorded yet.</div>
            @endforelse
        </div>
    </div>

    <!-- Non-Conformance Reports Summary -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-3">
        <flux:heading size="md">Non-Conformance Reports ({{ $audit->findings->count() }})</flux:heading>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($audit->findings as $f)
                <div class="py-3 first:pt-0 last:pb-0 flex items-center justify-between text-xs">
                    <div class="space-y-0.5">
                        <div class="flex items-center space-x-2">
                            <span class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $f->reference ?: $f->finding_number }}</span>
                            <flux:badge size="xs" :color="$f->level == 1 ? 'rose' : 'amber'">Level {{ $f->level }}</flux:badge>
                            <span class="text-zinc-500 font-mono">{{ $f->requirement_ref }}</span>
                        </div>
                        <div class="text-zinc-700 dark:text-zinc-300">{{ $f->description ?: $f->condition }}</div>
                    </div>
                    <div>
                        <flux:badge size="sm" :color="$f->status === 'closed' ? 'zinc' : ($f->status === 'cap_accepted' ? 'emerald' : 'rose')">
                            {{ ucfirst($f->status) }}
                        </flux:badge>
                    </div>
                </div>
            @empty
                <div class="py-4 text-center text-xs text-zinc-500">Zero non-conformances raised during this audit.</div>
            @endforelse
        </div>
    </div>

    <!-- RETURN AUDIT MODAL -->
    <flux:modal wire:model="showReturnModal" class="md:w-96 space-y-4">
        <div>
            <flux:heading size="lg">Return Audit with Comment</flux:heading>
            <flux:subheading>Return to preceding review stage for revisions</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:textarea wire:model="returnComment" label="Executive Feedback *" rows="3" required placeholder="Specify why the audit is being returned..." />

            <div class="flex justify-end space-x-2 pt-2">
                <flux:button wire:click="$set('showReturnModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="returnAudit" variant="danger">Confirm Return</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
