<?php

use App\Contracts\AiService;
use App\Models\Audit;
use App\Models\AuditApproval;
use App\Services\CertificateGenerator;
use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Audit Approval Decision')] class extends Component {
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
        $this->isGeneratingAi = true;

        $ncrSummary = $this->audit->findings->map(function ($f) {
            return "NCR {$f->reference} (Level {$f->level}): {$f->description} [Status: {$f->status}]";
        })->implode("\n");

        $answersSummary = $this->audit->answers->map(function ($a) {
            $val = is_array($a->value) ? implode(', ', $a->value) : $a->value;
            return "Q#{$a->question_key}: {$val} (Remark: {$a->comment})";
        })->take(10)->implode("\n");

        $auditContext = "Audit Title: {$this->audit->title}\n"
            ."Directorate: " . ($this->audit->directorate->code ?? 'DSD') . "\n"
            ."Compliance Rating: {$this->audit->compliance_score}%\n"
            ."Findings:\n{$ncrSummary}\n"
            ."Sample Answers:\n{$answersSummary}";

        $this->aiSummary = $ai->summariseAudit($auditContext);
        $this->audit->update(['ai_summary' => $this->aiSummary]);

        $this->isGeneratingAi = false;
        session()->flash('message', 'AI executive summary generated.');
    }

    public function saveAiSummary()
    {
        $this->audit->update(['ai_summary' => $this->aiSummary]);
        session()->flash('message', 'Summary changes saved.');
    }

    public function headApprove()
    {
        $this->audit->update([
            'status' => 'head_approved',
            'ai_summary' => $this->aiSummary,
        ]);

        AuditApproval::create([
            'audit_id' => $this->audit->id,
            'user_id' => auth()->id(),
            'role' => 'head_of_audit',
            'action' => 'head_approved',
            'comment' => $this->approvalComment ?: 'Reviewed and recommended for Director authorization.',
        ]);

        // Notify director users
        $directors = \App\Models\User::role('director')->get();
        foreach ($directors as $dir) {
            $dir->notify(new \App\Notifications\AuditAssigned($this->audit));
        }

        session()->flash('message', 'Audit endorsed by Head of Audit and escalated to Director of Special Duties.');

        return redirect()->route('approvals.index');
    }

    public function directorApprove(CertificateGenerator $generator)
    {
        $this->audit->update([
            'status' => 'director_approved',
            'end_date' => now(),
            'ai_summary' => $this->aiSummary,
        ]);

        AuditApproval::create([
            'audit_id' => $this->audit->id,
            'user_id' => auth()->id(),
            'role' => 'director',
            'action' => 'director_approved',
            'comment' => $this->approvalComment ?: 'Final Directorate statutory authorization granted.',
        ]);

        // Generate official certificate PDF + QR code
        $generator->generate($this->audit, auth()->id());

        session()->flash('message', 'Final Director approval granted and Quality Audit Certificate issued!');

        return redirect()->route('approvals.index');
    }

    public function returnAudit()
    {
        $this->validate(['returnComment' => 'required|string']);

        $user = auth()->user();
        $targetRole = $user->hasRole('director') ? 'head_of_audit' : 'reviewer';
        $newStatus = $user->hasRole('director') ? 'reviewed' : 'submitted';

        $this->audit->update(['status' => $newStatus]);

        AuditApproval::create([
            'audit_id' => $this->audit->id,
            'user_id' => $user->id,
            'role' => $user->roles->first()?->name ?? 'executive',
            'action' => 'returned',
            'comment' => $this->returnComment,
        ]);

        $this->showReturnModal = false;
        session()->flash('message', 'Audit returned for revisions.');

        return redirect()->route('approvals.index');
    }

    public function with()
    {
        $user = auth()->user();
        $isHead = $user->hasRole('head_of_audit') || $user->hasRole('system_admin');
        $isDirector = $user->hasRole('director') || $user->hasRole('system_admin');

        $questions = ! empty($this->audit->questions_snapshot)
            ? $this->audit->questions_snapshot
            : ($this->audit->process ? $this->audit->process->questions->toArray() : []);

        return [
            'isHead' => $isHead,
            'isDirector' => $isDirector,
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
                <flux:badge size="sm" :color="$audit->status === 'director_approved' ? 'emerald' : ($audit->status === 'head_approved' ? 'indigo' : 'blue')">
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

            @if($audit->status !== 'director_approved')
                <flux:button wire:click="$set('showReturnModal', true)" variant="danger" icon="arrow-uturn-left">
                    Return Audit
                </flux:button>

                @if($audit->status === 'reviewed' && $isHead)
                    <flux:button wire:click="headApprove" variant="primary" icon="check">
                        Endorse & Send to Director
                    </flux:button>
                @elseif($audit->status === 'head_approved' && $isDirector)
                    <flux:button wire:click="directorApprove" variant="primary" icon="check-badge">
                        Grant Final Approval & Certify
                    </flux:button>
                @endif
            @endif
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
            {{ session('message') }}
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
            <span class="text-zinc-500 uppercase font-semibold">Non-Conformances:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">{{ $audit->findings->count() }} Raised ({{ $audit->findings->where('status', 'closed')->count() }} Closed)</div>
        </div>
        <div>
            <span class="text-zinc-500 uppercase font-semibold">Audit Reviewer:</span>
            <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">{{ $audit->reviewer->name ?? 'None' }}</div>
        </div>
    </div>

    <!-- AI Executive Summary Card -->
    <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="md">Executive Summary & Audit Brief</flux:heading>
                <flux:subheading>Formal 3-paragraph executive briefing for the Director of Special Duties</flux:subheading>
            </div>

            <div class="flex items-center space-x-2">
                <flux:button wire:click="generateAiSummary" variant="ghost" size="sm" icon="sparkles" wire:loading.attr="disabled">
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
