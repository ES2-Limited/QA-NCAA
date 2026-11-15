<?php

use App\Models\Attachment;
use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Notifications\AuditSubmitted;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.field.mobile')] #[Title('Conduct Audit — NCAA Field Execution')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?Audit $audit = null;
    public array $questions = [];
    public int $currentIndex = 0; // 0-based question pointer
    public bool $isReviewMode = false;
    public bool $showDrawer = false;

    // Active question form fields
    public $activeValue = null; // string for radio/text, array for checkbox
    public string $activeComment = '';
    public array $newPhotos = [];
    public array $newFiles = [];

    // Geolocation state
    public ?float $lat = null;
    public ?float $lng = null;
    public ?float $locationAccuracy = null;
    public string $locationStatus = 'pending'; // 'captured', 'unavailable', 'pending'

    public function mount($audit)
    {
        $this->audit = $audit instanceof Audit
            ? $audit->loadMissing(['auditors', 'reviewer', 'answers.attachments', 'process'])
            : Audit::with(['auditors', 'reviewer', 'answers.attachments', 'process'])->findOrFail($audit);

        // Security check: field auditor can only open audits they are assigned to
        $isAssigned = $this->audit->auditors->contains(auth()->id());
        if (! $isAssigned && ! auth()->user()->hasRole('system_admin')) {
            abort(403, 'Unauthorized. You are not assigned to this audit engagement.');
        }

        $this->questions = ! empty($this->audit->questions_snapshot)
            ? $this->audit->questions_snapshot
            : ($this->audit->process ? $this->audit->process->questions->toArray() : []);

        $this->loadQuestionData($this->currentIndex);
    }

    public function startAudit()
    {
        if ($this->audit->status === 'scheduled') {
            $this->audit->update([
                'status' => 'in_progress',
                'start_date' => now(),
            ]);
        }
    }

    public function loadQuestionData(int $index)
    {
        $this->currentIndex = $index;
        $this->newPhotos = [];
        $this->newFiles = [];

        $answer = $this->audit->answers()->where('question_key', $index)->first();

        if ($answer) {
            $this->activeValue = $answer->value;
            $this->activeComment = $answer->comment ?? '';
            $this->lat = $answer->lat;
            $this->lng = $answer->lng;
            $this->locationAccuracy = $answer->location_accuracy;
            $this->locationStatus = ($this->lat && $this->lng) ? 'captured' : 'unavailable';
        } else {
            $currentQ = $this->questions[$index] ?? null;
            $type = $currentQ['type'] ?? 'radio';
            $this->activeValue = ($type === 'checkbox') ? [] : null;
            $this->activeComment = '';
            $this->lat = null;
            $this->lng = null;
            $this->locationAccuracy = null;
            $this->locationStatus = 'pending';
        }
    }

    public function setLocation(?float $lat = null, ?float $lng = null, ?float $accuracy = null)
    {
        if ($lat !== null && $lng !== null) {
            $this->lat = $lat;
            $this->lng = $lng;
            $this->locationAccuracy = $accuracy;
            $this->locationStatus = 'captured';
        } else {
            $this->locationStatus = 'unavailable';
        }
    }

    public function saveAnswer(bool $advance = false)
    {
        // Don't allow edits if already submitted
        if ($this->isReadOnly()) {
            return;
        }

        $this->validate([
            'newPhotos.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
            'newFiles.*' => 'nullable|file|mimes:pdf,docx,doc|max:20480',
        ]);

        // Auto-start audit on first save
        if ($this->audit->status === 'scheduled') {
            $this->audit->update(['status' => 'in_progress']);
        }

        $answer = AuditAnswer::updateOrCreate(
            [
                'audit_id' => $this->audit->id,
                'question_key' => $this->currentIndex,
            ],
            [
                'value' => $this->activeValue,
                'comment' => $this->activeComment ?: null,
                'lat' => $this->lat,
                'lng' => $this->lng,
                'location_accuracy' => $this->locationAccuracy,
                'answered_at' => now(),
                'answered_by' => auth()->id(),
            ]
        );

        // Process photo uploads (stored on private local disk)
        foreach ($this->newPhotos as $photo) {
            $path = $photo->store('evidence', 'local');
            $answer->attachments()->create([
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType(),
                'size' => $photo->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        // Process file uploads (PDF/DOCX)
        foreach ($this->newFiles as $file) {
            $path = $file->store('evidence', 'local');
            $answer->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->newPhotos = [];
        $this->newFiles = [];
        $this->audit->load('answers.attachments');

        if ($advance) {
            if ($this->currentIndex < count($this->questions) - 1) {
                $this->loadQuestionData($this->currentIndex + 1);
            } else {
                $this->isReviewMode = true;
            }
        }
    }

    public function prevQuestion()
    {
        $this->saveAnswer(false);
        if ($this->currentIndex > 0) {
            $this->loadQuestionData($this->currentIndex - 1);
        }
    }

    public function nextQuestion()
    {
        $this->saveAnswer(true);
    }

    public function jumpToQuestion(int $index)
    {
        $this->saveAnswer(false);
        $this->isReviewMode = false;
        $this->showDrawer = false;
        $this->loadQuestionData($index);
    }

    public function removeAttachment(int $attachmentId)
    {
        if ($this->isReadOnly()) {
            return;
        }

        $att = Attachment::find($attachmentId);
        if ($att) {
            if (Storage::disk('local')->exists($att->path)) {
                Storage::disk('local')->delete($att->path);
            } elseif (Storage::disk('public')->exists($att->path)) {
                Storage::disk('public')->delete($att->path);
            }
            $att->delete();
            $this->audit->load('answers.attachments');
        }
    }

    public function submitAudit()
    {
        if ($this->isReadOnly()) {
            return;
        }

        // Validate all questions answered
        $totalQuestions = count($this->questions);
        $answers = $this->audit->answers()->pluck('value', 'question_key')->toArray();

        $unanswered = [];
        for ($i = 0; $i < $totalQuestions; $i++) {
            if (! array_key_exists($i, $answers) || $answers[$i] === null || $answers[$i] === '' || $answers[$i] === []) {
                $unanswered[] = $i + 1;
            }
        }

        if (count($unanswered) > 0) {
            session()->flash('error', 'Cannot submit audit. Questions remaining: #' . implode(', #', $unanswered));
            return;
        }

        $this->audit->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Notify reviewer
        if ($this->audit->reviewer) {
            $this->audit->reviewer->notify(new AuditSubmitted($this->audit, auth()->user()));
        }

        session()->flash('message', 'Audit successfully submitted for review. It is now locked as read-only.');
        $this->isReviewMode = false;
    }

    public function isReadOnly(): bool
    {
        return in_array($this->audit->status, ['submitted', 'reviewed', 'head_approved', 'director_approved']);
    }

    public function with()
    {
        $total = count($this->questions);
        $answeredCount = $this->audit->answers->filter(function ($a) {
            return $a->value !== null && $a->value !== '' && $a->value !== [];
        })->count();

        $currentAnswer = $this->audit->answers->firstWhere('question_key', $this->currentIndex);
        $attachments = $currentAnswer ? $currentAnswer->attachments : collect();

        return [
            'total' => $total,
            'answeredCount' => $answeredCount,
            'currentAnswer' => $currentAnswer,
            'attachments' => $attachments,
            'readOnly' => $this->isReadOnly(),
        ];
    }
}; ?>

<div class="space-y-4" x-data="{
    captureLocation() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    $wire.setLocation(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                },
                (err) => {
                    console.log('Location unavailable:', err);
                    $wire.setLocation(null, null, null);
                },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        } else {
            $wire.setLocation(null, null, null);
        }
    }
}" x-init="captureLocation()">

    <!-- Top Audit Header Card -->
    <div class="p-3 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-2">
        <div class="flex items-center justify-between text-xs">
            <a href="{{ route('field.index') }}" wire:navigate class="text-zinc-500 hover:text-zinc-900 font-semibold flex items-center gap-1">
                &larr; Audits Hub
            </a>
            <div class="flex items-center gap-2">
                @if($readOnly)
                    <flux:badge size="sm" color="zinc">Submitted &bull; Locked</flux:badge>
                @else
                    <flux:badge size="sm" :color="$audit->status === 'in_progress' ? 'amber' : 'zinc'">
                        {{ ucwords(str_replace('_', ' ', $audit->status)) }}
                    </flux:badge>
                @endif
            </div>
        </div>

        <div class="flex items-start justify-between gap-2">
            <div>
                <h1 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 leading-snug">
                    {{ $audit->title }}
                </h1>
                <div class="text-[11px] text-zinc-500 font-mono mt-0.5">
                    {{ $audit->reference_number }} &bull; {{ $audit->directorate->code ?? 'DSD' }}
                </div>
            </div>

            <button type="button" wire:click="$toggle('showDrawer')" class="p-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-xs font-bold shrink-0">
                📑 {{ $answeredCount }}/{{ $total }}
            </button>
        </div>

        <!-- Progress bar -->
        <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
            @php $pct = $total > 0 ? (int) round(($answeredCount / $total) * 100) : 0; @endphp
            <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-300" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-xs font-medium">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-3 bg-rose-50 dark:bg-rose-950/60 text-rose-800 dark:text-rose-200 border border-rose-300 dark:border-rose-800 rounded-xl text-xs font-medium">
            {{ session('error') }}
        </div>
    @endif

    <!-- Start Audit Action Banner for Scheduled status -->
    @if($audit->status === 'scheduled')
        <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl text-center space-y-2">
            <div class="text-xs font-bold text-amber-900 dark:text-amber-200">
                This audit is scheduled. Ready to commence field inspection?
            </div>
            <flux:button wire:click="startAudit" variant="primary" icon="play" class="w-full">
                Start Audit Now
            </flux:button>
        </div>
    @elseif($audit->status === 'returned')
        <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl space-y-1">
            <div class="text-xs font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
                <flux:icon.exclamation-triangle class="size-4 text-amber-600" />
                Audit Returned by Reviewer
            </div>
            <p class="text-[11px] text-amber-800 dark:text-amber-300">
                The reviewer returned this audit for clarifications or additional evidence. Update the relevant answers and resubmit.
            </p>
        </div>
    @endif

    <!-- REVIEW & SUBMIT SCREEN -->
    @if($isReviewMode)
        <div class="p-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-black text-zinc-900 dark:text-zinc-100">Review & Submit</h2>
                    <p class="text-xs text-zinc-500">Verify all questions before finalizing audit submission.</p>
                </div>
                <button wire:click="$set('isReviewMode', false)" class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                    &larr; Back to Questions
                </button>
            </div>

            <!-- Answers list -->
            <div class="space-y-2 max-h-96 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($questions as $idx => $q)
                    @php
                        $ans = $audit->answers->firstWhere('question_key', $idx);
                        $hasAnswer = $ans && $ans->value !== null && $ans->value !== '' && $ans->value !== [];
                    @endphp
                    <div class="pt-2.5 first:pt-0 flex items-start justify-between gap-2 cursor-pointer" wire:click="jumpToQuestion({{ $idx }})">
                        <div class="space-y-0.5">
                            <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200">
                                #{{ $idx + 1 }}. {{ Str::limit($q['question'], 48) }}
                            </div>
                            <div class="text-[11px] text-zinc-500">
                                @if($hasAnswer)
                                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">
                                        Answer: {{ is_array($ans->value) ? implode(', ', $ans->value) : $ans->value }}
                                    </span>
                                @else
                                    <span class="text-rose-500 font-bold">⚠️ Unanswered</span>
                                @endif
                            </div>
                        </div>

                        <span class="text-xs font-mono {{ $hasAnswer ? 'text-emerald-600' : 'text-rose-500' }}">
                            {{ $hasAnswer ? '✓' : '✗' }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-zinc-200 dark:border-zinc-800 space-y-2">
                @if($answeredCount < $total)
                    <div class="p-2.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-xs text-rose-800 dark:text-rose-200 text-center font-medium">
                        {{ $total - $answeredCount }} question(s) remaining. Complete all to submit.
                    </div>
                @endif

                @if(! $readOnly)
                    <flux:button 
                        wire:click="submitAudit" 
                        variant="primary" 
                        icon="check-circle" 
                        class="w-full !py-3 font-bold" 
                        :disabled="$answeredCount < $total">
                        Submit Audit for Review
                    </flux:button>
                @endif
            </div>
        </div>
    @else
        <!-- ACTIVE QUESTION CARD (ONE PER CARD) -->
        @php
            $currentQ = $questions[$currentIndex] ?? null;
        @endphp

        @if($currentQ)
            <div class="p-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 space-y-4 shadow-sm">
                <!-- Question Header -->
                <div class="flex items-center justify-between text-xs text-zinc-500">
                    <span class="font-mono font-bold bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded text-zinc-800 dark:text-zinc-200">
                        Question {{ $currentIndex + 1 }} of {{ $total }}
                    </span>

                    <!-- Geolocation badge -->
                    <div class="flex items-center gap-1">
                        @if($locationStatus === 'captured')
                            <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">📍 Location captured</span>
                        @elseif($locationStatus === 'unavailable')
                            <span class="text-[11px] text-zinc-400">Location unavailable</span>
                        @else
                            <span class="text-[11px] text-zinc-400 animate-pulse">Acquiring GPS...</span>
                        @endif
                    </div>
                </div>

                <!-- Question Text -->
                <div class="space-y-1">
                    <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 leading-snug">
                        {{ $currentQ['question'] }}
                    </h2>
                    @if(!empty($currentQ['requirement_ref']))
                        <div class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400">
                            Ref: {{ $currentQ['requirement_ref'] }}
                        </div>
                    @endif
                </div>

                <!-- Collapsible Guidance -->
                @if(!empty($currentQ['guidance']))
                    <div x-data="{ open: false }" class="bg-zinc-50 dark:bg-zinc-800/60 rounded-xl p-2.5 text-xs border border-zinc-200 dark:border-zinc-700/60">
                        <button type="button" @click="open = !open" class="w-full flex items-center justify-between font-bold text-zinc-700 dark:text-zinc-300">
                            <span>🔍 Inspection Guidance</span>
                            <span x-text="open ? '▲' : '▼'"></span>
                        </button>
                        <p x-show="open" class="mt-1 text-zinc-600 dark:text-zinc-400 italic">
                            {{ $currentQ['guidance'] }}
                        </p>
                    </div>
                @endif

                <!-- INPUT CONTROLS BY TYPE -->
                <div class="pt-2 space-y-3">
                    @if(($currentQ['type'] ?? 'radio') === 'radio')
                        <!-- Radio Options -->
                        <div class="space-y-2">
                            @foreach($currentQ['options'] ?? ['Compliant', 'Non-compliant', 'N/A'] as $opt)
                                <label class="flex items-center justify-between p-3 rounded-xl border border-zinc-200 dark:border-zinc-750 bg-zinc-50 dark:bg-zinc-800/40 cursor-pointer active:scale-[0.99] transition {{ $activeValue === $opt ? 'border-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/30' : '' }}">
                                    <div class="flex items-center space-x-2.5">
                                        <input type="radio" wire:model.live="activeValue" value="{{ $opt }}" class="text-emerald-600 focus:ring-emerald-500 size-4" {{ $readOnly ? 'disabled' : '' }}>
                                        <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $opt }}</span>
                                    </div>
                                    @if($activeValue === $opt)
                                        <span class="text-emerald-600 text-xs font-bold">✓ Selected</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @elseif(($currentQ['type'] ?? '') === 'checkbox')
                        <!-- Multiple Checkbox Options -->
                        <div class="space-y-2">
                            @foreach($currentQ['options'] ?? ['Verified', 'Not Verified'] as $opt)
                                <label class="flex items-center space-x-2.5 p-3 rounded-xl border border-zinc-200 dark:border-zinc-750 bg-zinc-50 dark:bg-zinc-800/40 cursor-pointer">
                                    <input type="checkbox" wire:model.live="activeValue" value="{{ $opt }}" class="rounded text-emerald-600 focus:ring-emerald-500 size-4" {{ $readOnly ? 'disabled' : '' }}>
                                    <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $opt }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <!-- Textarea narrative -->
                        <flux:textarea 
                            wire:model.live.debounce.500ms="activeValue" 
                            rows="3" 
                            placeholder="Enter detailed audit verification observation..." 
                            :disabled="$readOnly" 
                        />
                    @endif

                    <!-- Comment Field (Optional) -->
                    <div class="pt-1">
                        <flux:input 
                            wire:model.live.debounce.500ms="activeComment" 
                            label="Auditor Remark (Optional)" 
                            placeholder="Add specific notes, tags, or discrepancy detail..." 
                            :disabled="$readOnly" 
                        />
                    </div>

                    <!-- EVIDENCE UPLOAD & THUMBNAILS -->
                    <div class="pt-2 space-y-2">
                        <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300">
                            Evidence & Supporting Media (Max 10 MB each)
                        </label>

                        @if(! $readOnly)
                            <div class="grid grid-cols-2 gap-2">
                                <!-- Camera Capture Button -->
                                <label class="flex items-center justify-center gap-1.5 p-2.5 rounded-xl border border-dashed border-emerald-400 bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-300 text-xs font-bold cursor-pointer hover:bg-emerald-100 transition text-center">
                                    <flux:icon name="camera" class="size-4" />
                                    <span>Take Photo</span>
                                    <input type="file" wire:model="newPhotos" accept="image/*" capture="environment" multiple class="hidden">
                                </label>

                                <!-- Document / File Attach Button -->
                                <label class="flex items-center justify-center gap-1.5 p-2.5 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs font-bold cursor-pointer hover:bg-zinc-100 transition text-center">
                                    <flux:icon name="paper-clip" class="size-4" />
                                    <span>Attach File</span>
                                    <input type="file" wire:model="newFiles" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple class="hidden">
                                </label>
                            </div>

                            <div wire:loading wire:target="newPhotos, newFiles" class="text-xs text-emerald-600 font-medium">
                                Uploading evidence...
                            </div>
                        @endif

                        <!-- Thumbnails list -->
                        @if($attachments->count() > 0)
                            <div class="grid grid-cols-3 gap-2 pt-2">
                                @foreach($attachments as $att)
                                    <div class="relative group rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-zinc-100 dark:bg-zinc-800 p-1">
                                        @if(str_starts_with($att->mime_type, 'image/'))
                                            <img src="{{ route('attachments.show', $att->id) }}" alt="Evidence" class="w-full h-16 object-cover rounded-lg">
                                        @else
                                            <div class="w-full h-16 flex flex-col items-center justify-center text-[10px] text-zinc-600 dark:text-zinc-400 font-bold p-1">
                                                <span>📄 FILE</span>
                                                <span class="truncate max-w-full text-[9px]">{{ $att->original_name }}</span>
                                            </div>
                                        @endif

                                        @if(! $readOnly)
                                            <button 
                                                type="button" 
                                                wire:click="removeAttachment({{ $att->id }})" 
                                                class="absolute top-1 right-1 bg-rose-600 text-white rounded-full size-4 flex items-center justify-center text-[10px] font-black shadow"
                                                title="Remove">
                                                &times;
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Navigation Action Buttons (Previous / Next / Review) -->
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between gap-2">
                    <flux:button 
                        wire:click="prevQuestion" 
                        variant="ghost" 
                        size="sm" 
                        :disabled="$currentIndex === 0">
                        &larr; Prev
                    </flux:button>

                    <button 
                        type="button" 
                        wire:click="saveAnswer(false)" 
                        class="text-xs text-zinc-400 hover:text-zinc-600 font-medium">
                        Save
                    </button>

                    @if($currentIndex < $total - 1)
                        <flux:button 
                            wire:click="nextQuestion" 
                            variant="primary" 
                            size="sm">
                            Next &rarr;
                        </flux:button>
                    @else
                        <flux:button 
                            wire:click="$set('isReviewMode', true)" 
                            variant="primary" 
                            size="sm">
                            Review &rarr;
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    @endif

    <!-- Question Jumping Drawer Modal -->
    <flux:modal wire:model="showDrawer" class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="md">Audit Question List</flux:heading>
            <span class="text-xs text-zinc-500 font-bold">{{ $answeredCount }}/{{ $total }} answered</span>
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach($questions as $idx => $q)
                @php
                    $ans = $audit->answers->firstWhere('question_key', $idx);
                    $hasAnswer = $ans && $ans->value !== null && $ans->value !== '' && $ans->value !== [];
                @endphp
                <button 
                    type="button" 
                    wire:click="jumpToQuestion({{ $idx }})" 
                    class="w-full text-left py-2.5 px-2 flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition {{ $currentIndex === $idx ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-200' : '' }}">
                    <div class="truncate mr-2">
                        <span class="font-bold text-xs">#{{ $idx + 1 }}.</span>
                        <span class="text-xs">{{ Str::limit($q['question'], 40) }}</span>
                    </div>
                    <span class="text-xs {{ $hasAnswer ? 'text-emerald-600' : 'text-zinc-400' }}">
                        {{ $hasAnswer ? '✓' : '—' }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700 flex justify-between">
            <flux:button wire:click="$set('showDrawer', false)" variant="ghost">Close</flux:button>
            <flux:button wire:click="$set('isReviewMode', true); $set('showDrawer', false);" variant="primary">Review Screen</flux:button>
        </div>
    </flux:modal>
</div>
