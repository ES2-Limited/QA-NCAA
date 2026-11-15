<?php

use App\Contracts\AiService as AiServiceContract;
use App\Models\ChecklistQuestion;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Document;
use App\Models\Process;
use App\Models\Unit;
use App\Services\DocumentTextExtractor;
use App\Services\GeminiAiService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Process & Checklist Wizard')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?Process $process = null;
    public $step = 1;

    // Step 1: Details
    public $name = '';
    public $description = '';
    public $directorate_id = '';
    public $department_id = '';
    public $unit_id = '';

    // Step 2: Documents
    public $selectedDocumentIds = [];
    public $documentSearch = '';
    // Inline upload
    public $inlineTitle = '';
    public $inlineFile;

    // Step 3: Schedule
    public $type = 'routine'; // routine, unplanned
    public $frequency = 'monthly'; // weekly, monthly, quarterly, yearly
    public $first_due_date = '';

    // Step 4: Checklist Questions
    public $questions = []; // array of items

    // AI Generation Modal
    public $showAiModal = false;
    public $aiScope = '';
    public $aiCount = 8;
    public $aiDocumentId = '';
    public $isGeneratingAi = false;

    public function mount($process = null)
    {
        if ($process) {
            $this->process = Process::with(['documents', 'questions'])->findOrFail($process);
            $this->name = $this->process->name;
            $this->description = $this->process->description ?? '';
            $this->directorate_id = $this->process->directorate_id ?? '';
            $this->department_id = $this->process->department_id ?? '';
            $this->unit_id = $this->process->unit_id ?? '';
            $this->type = $this->process->type;
            $this->frequency = $this->process->frequency ?? 'monthly';
            $this->first_due_date = $this->process->next_due_at ? $this->process->next_due_at->format('Y-m-d') : '';
            $this->selectedDocumentIds = $this->process->documents->pluck('id')->toArray();

            foreach ($this->process->questions as $q) {
                $this->questions[] = [
                    'id' => $q->id,
                    'question' => $q->question,
                    'type' => $q->type,
                    'options' => $q->options ?: ['Compliant', 'Non-compliant', 'N/A'],
                    'requirement_ref' => $q->requirement_ref ?? '',
                    'guidance' => $q->guidance ?? '',
                    'source' => $q->source,
                ];
            }
        } else {
            $this->first_due_date = now()->addDays(14)->format('Y-m-d');
        }
    }

    public function updatedDirectorateId()
    {
        $this->department_id = '';
        $this->unit_id = '';
    }

    public function updatedDepartmentId()
    {
        $this->unit_id = '';
    }

    public function setStep($s)
    {
        $this->validateStep($this->step);
        $this->step = $s;
    }

    public function nextStep()
    {
        $this->validateStep($this->step);
        $this->step = min(5, $this->step + 1);
    }

    public function prevStep()
    {
        $this->step = max(1, $this->step - 1);
    }

    protected function validateStep($step)
    {
        if ($step === 1) {
            $this->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'directorate_id' => 'required|exists:directorates,id',
                'department_id' => 'nullable|exists:departments,id',
                'unit_id' => 'nullable|exists:units,id',
            ]);
        } elseif ($step === 3) {
            if ($this->type === 'routine') {
                $this->validate([
                    'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
                    'first_due_date' => 'required|date',
                ]);
            }
        }
    }

    // Inline Document Upload
    public function uploadInlineDocument()
    {
        $this->validate([
            'inlineTitle' => 'required|string|max:255',
            'inlineFile' => 'required|file|mimes:pdf,docx|max:20480',
        ]);

        $path = $this->inlineFile->store('documents', 'local');

        $doc = Document::create([
            'title' => $this->inlineTitle,
            'file_path' => $path,
            'original_name' => $this->inlineFile->getClientOriginalName(),
            'mime_type' => $this->inlineFile->getMimeType(),
            'size' => $this->inlineFile->getSize(),
            'unit_id' => $this->unit_id ?: null,
            'uploaded_by' => auth()->id() ?? 1,
        ]);

        $this->selectedDocumentIds[] = $doc->id;
        $this->reset(['inlineTitle', 'inlineFile']);
        session()->flash('doc_message', 'Document uploaded and linked to process.');
    }

    // Checklist Question Management
    public function addQuestion()
    {
        $this->questions[] = [
            'question' => '',
            'type' => 'radio',
            'options' => ['Compliant', 'Non-compliant', 'N/A'],
            'requirement_ref' => '',
            'guidance' => '',
            'source' => 'manual',
        ];
    }

    public function removeQuestion($index)
    {
        unset($this->questions[$index]);
        $this->questions = array_values($this->questions);
    }

    public function moveQuestionUp($index)
    {
        if ($index > 0) {
            $temp = $this->questions[$index - 1];
            $this->questions[$index - 1] = $this->questions[$index];
            $this->questions[$index] = $temp;
        }
    }

    public function moveQuestionDown($index)
    {
        if ($index < count($this->questions) - 1) {
            $temp = $this->questions[$index + 1];
            $this->questions[$index + 1] = $this->questions[$index];
            $this->questions[$index] = $temp;
        }
    }

    public function addOption($qIndex)
    {
        $this->questions[$qIndex]['options'][] = 'New Option';
    }

    public function removeOption($qIndex, $optIndex)
    {
        unset($this->questions[$qIndex]['options'][$optIndex]);
        $this->questions[$qIndex]['options'] = array_values($this->questions[$qIndex]['options']);
    }

    // AI Generation
    public function openAiModal()
    {
        $this->aiScope = $this->name ?: 'Aviation Quality & Safety Operations';
        $this->aiCount = 8;
        $this->aiDocumentId = ! empty($this->selectedDocumentIds) ? (string) $this->selectedDocumentIds[0] : '';
        $this->showAiModal = true;
    }

    public function generateWithAi(AiServiceContract $ai, DocumentTextExtractor $extractor)
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
            session()->flash('error', "AI rate limit reached. Please wait {$seconds} seconds before generating more questions.");
            return;
        }

        $this->validate([
            'aiScope' => 'required|string|max:255',
            'aiCount' => 'required|integer|min:1|max:20',
        ]);

        $this->isGeneratingAi = true;

        $requirementText = '';
        if (! empty($this->aiDocumentId)) {
            $doc = Document::find($this->aiDocumentId);
            if ($doc) {
                $requirementText = $extractor->extract($doc);
            }
        }

        $generated = $ai->generateChecklist($requirementText, $this->aiScope, (int) $this->aiCount);

        foreach ($generated as $item) {
            $this->questions[] = [
                'question' => $item['question'],
                'type' => $item['type'] ?? 'radio',
                'options' => $item['options'] ?? ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => $item['requirement_ref'] ?? '',
                'guidance' => $item['guidance'] ?? '',
                'source' => 'ai',
            ];
        }

        $this->isGeneratingAi = false;
        $this->showAiModal = false;
        session()->flash('message', count($generated) . ' draft questions generated with AI. You can review and edit them below.');
    }

    // Save Handlers
    public function save($status = 'draft')
    {
        // Global validation
        $this->validate([
            'name' => 'required|string|max:255',
            'directorate_id' => 'required|exists:directorates,id',
            'department_id' => 'nullable|exists:departments,id',
            'unit_id' => 'nullable|exists:units,id',
            'type' => 'required|in:routine,unplanned',
        ]);

        if ($this->type === 'routine') {
            $this->validate([
                'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
                'first_due_date' => 'required|date',
            ]);
        }

        if ($status === 'active' && count($this->questions) === 0) {
            $this->addError('questions', 'At least 1 question is required to activate a process template.');
            $this->step = 4;
            return;
        }

        $nextDue = ($this->type === 'routine' && $this->first_due_date)
            ? Carbon::parse($this->first_due_date)
            : null;

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'directorate_id' => $this->directorate_id,
            'department_id' => $this->department_id ?: null,
            'unit_id' => $this->unit_id ?: null,
            'type' => $this->type,
            'frequency' => $this->type === 'routine' ? $this->frequency : null,
            'next_due_at' => $nextDue,
            'created_by' => auth()->id() ?? 1,
            'status' => $status,
        ];

        if ($this->process) {
            $this->process->update($data);
            $processModel = $this->process;
        } else {
            $processModel = Process::create($data);
        }

        // Sync Documents
        $processModel->documents()->sync($this->selectedDocumentIds);

        // Sync Questions
        $processModel->questions()->delete();

        foreach ($this->questions as $idx => $q) {
            ChecklistQuestion::create([
                'process_id' => $processModel->id,
                'order' => $idx + 1,
                'question' => $q['question'],
                'type' => $q['type'] ?? 'radio',
                'options' => in_array($q['type'], ['radio', 'checkbox']) ? ($q['options'] ?? ['Compliant', 'Non-compliant', 'N/A']) : null,
                'requirement_ref' => $q['requirement_ref'] ?? null,
                'guidance' => $q['guidance'] ?? null,
                'source' => $q['source'] ?? 'manual',
            ]);
        }

        return redirect()->route('processes.show', $processModel->id)
            ->with('message', 'Process "' . $processModel->name . '" saved successfully as ' . $status . '.');
    }

    public function with()
    {
        $departments = !empty($this->directorate_id)
            ? Department::where('directorate_id', $this->directorate_id)->orderBy('name')->get()
            : collect();

        $units = !empty($this->department_id)
            ? Unit::where('department_id', $this->department_id)->orderBy('name')->get()
            : collect();

        $docsQuery = Document::with('unit.department')->latest();
        if (!empty($this->documentSearch)) {
            $docsQuery->where('title', 'like', '%' . $this->documentSearch . '%');
        }

        return [
            'directorates' => Directorate::orderBy('name')->get(),
            'departments' => $departments,
            'units' => $units,
            'availableDocuments' => $docsQuery->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('processes.index') }}" class="hover:underline" wire:navigate>Processes</a>
                <span>/</span>
                <span>{{ $process ? 'Edit Process' : 'New Process Wizard' }}</span>
            </div>
            <flux:heading size="xl">{{ $process ? 'Edit Process: ' . $process->name : 'Create Process & Checklist' }}</flux:heading>
        </div>
    </div>

    <!-- Stepper Navigation Header -->
    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 overflow-x-auto text-xs font-semibold">
        <button wire:click="setStep(1)" class="flex items-center space-x-2 px-3 py-2 rounded-lg transition {{ $step === 1 ? 'bg-emerald-700 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] {{ $step === 1 ? 'bg-white text-emerald-800 font-bold' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">1</span>
            <span>Details</span>
        </button>

        <button wire:click="setStep(2)" class="flex items-center space-x-2 px-3 py-2 rounded-lg transition {{ $step === 2 ? 'bg-emerald-700 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] {{ $step === 2 ? 'bg-white text-emerald-800 font-bold' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">2</span>
            <span>Documents ({{ count($selectedDocumentIds) }})</span>
        </button>

        <button wire:click="setStep(3)" class="flex items-center space-x-2 px-3 py-2 rounded-lg transition {{ $step === 3 ? 'bg-emerald-700 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] {{ $step === 3 ? 'bg-white text-emerald-800 font-bold' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">3</span>
            <span>Schedule</span>
        </button>

        <button wire:click="setStep(4)" class="flex items-center space-x-2 px-3 py-2 rounded-lg transition {{ $step === 4 ? 'bg-emerald-700 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] {{ $step === 4 ? 'bg-white text-emerald-800 font-bold' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">4</span>
            <span>Checklist Builder ({{ count($questions) }})</span>
        </button>

        <button wire:click="setStep(5)" class="flex items-center space-x-2 px-3 py-2 rounded-lg transition {{ $step === 5 ? 'bg-emerald-700 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] {{ $step === 5 ? 'bg-white text-emerald-800 font-bold' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}">5</span>
            <span>Review & Save</span>
        </button>
    </div>

    <!-- STEP 1: DETAILS -->
    @if($step === 1)
        <div class="p-6 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-5">
            <flux:heading size="lg">Step 1: Process Details & Hierarchy</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="name" label="Process Name *" placeholder="e.g. Approved Maintenance Organization (AMO) Surveillance Audit" required />

                <flux:textarea wire:model="description" label="Scope & Description" rows="3" placeholder="Outline the statutory purpose, objective, and facility scope..." />

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <flux:select wire:model.live="directorate_id" label="Directorate *" required>
                        <flux:select.option value="">Select Directorate</flux:select.option>
                        @foreach($directorates as $dir)
                            <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} - {{ $dir->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="department_id" label="Department" :disabled="empty($directorate_id)">
                        <flux:select.option value="">None / Directorate-Wide</flux:select.option>
                        @foreach($departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="unit_id" label="Operational Unit" :disabled="empty($department_id)">
                        <flux:select.option value="">None / Dept-Wide</flux:select.option>
                        @foreach($units as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <flux:button wire:click="nextStep" variant="primary">Next: Link Documents &rarr;</flux:button>
            </div>
        </div>
    @endif

    <!-- STEP 2: DOCUMENTS -->
    @if($step === 2)
        <div class="p-6 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-6">
            <flux:heading size="lg">Step 2: Reference Quality Documents</flux:heading>

            @if(session()->has('doc_message'))
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 rounded-xl text-sm">
                    {{ session('doc_message') }}
                </div>
            @endif

            <!-- Select from Library -->
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <flux:label>Pick from Document Library ({{ count($selectedDocumentIds) }} selected)</flux:label>
                    <div class="w-64">
                        <flux:input wire:model.live.debounce.250ms="documentSearch" placeholder="Search library..." icon="magnifying-glass" size="sm" />
                    </div>
                </div>

                <div class="max-h-64 overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700 rounded-xl border border-zinc-200 dark:border-zinc-700 p-2 space-y-1">
                    @forelse($availableDocuments as $doc)
                        <label class="flex items-center justify-between p-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-700/50 cursor-pointer">
                            <div class="flex items-center space-x-3">
                                <input type="checkbox" wire:model.live="selectedDocumentIds" value="{{ $doc->id }}" class="rounded text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $doc->title }}</div>
                                    <div class="text-xs text-zinc-500">{{ $doc->original_name }} &bull; {{ $doc->unit->name ?? 'General' }}</div>
                                </div>
                            </div>
                            <span class="text-xs font-mono text-zinc-400 uppercase">{{ str_contains($doc->mime_type, 'pdf') ? 'PDF' : 'DOCX' }}</span>
                        </label>
                    @empty
                        <div class="p-4 text-center text-xs text-zinc-500">No matching documents found in library.</div>
                    @endforelse
                </div>
            </div>

            <!-- Upload New Inline Document -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 space-y-3">
                <flux:heading size="sm">Or Upload New Document to Library</flux:heading>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:input wire:model="inlineTitle" placeholder="Document Title (e.g. Nig.CARs Part 6 Annex)" />
                    <flux:input wire:model="inlineFile" type="file" />
                </div>
                <div class="flex justify-end">
                    <flux:button wire:click="uploadInlineDocument" size="sm" variant="filled">Upload & Link</flux:button>
                </div>
            </div>

            <div class="flex justify-between pt-4">
                <flux:button wire:click="prevStep" variant="ghost">&larr; Back</flux:button>
                <flux:button wire:click="nextStep" variant="primary">Next: Schedule &rarr;</flux:button>
            </div>
        </div>
    @endif

    <!-- STEP 3: SCHEDULE -->
    @if($step === 3)
        <div class="p-6 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-5">
            <flux:heading size="lg">Step 3: Audit Execution Schedule</flux:heading>

            <div class="space-y-4 max-w-xl">
                <flux:radio.group wire:model.live="type" label="Audit Engagement Type">
                    <flux:radio value="routine" label="Routine (Recurring Quality Assurance Audit)" description="Audits automatically scheduled according to fixed frequency intervals." />
                    <flux:radio value="unplanned" label="Unplanned / Spot Check" description="Ad-hoc, special duty investigations or immediate surprise surveillance." />
                </flux:radio.group>

                @if($type === 'routine')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <flux:select wire:model="frequency" label="Frequency *" required>
                            <flux:select.option value="weekly">Weekly</flux:select.option>
                            <flux:select.option value="monthly">Monthly</flux:select.option>
                            <flux:select.option value="quarterly">Quarterly</flux:select.option>
                            <flux:select.option value="yearly">Yearly</flux:select.option>
                        </flux:select>

                        <flux:input wire:model="first_due_date" type="date" label="First Audit Due Date *" required />
                    </div>
                @endif
            </div>

            <div class="flex justify-between pt-4">
                <flux:button wire:click="prevStep" variant="ghost">&larr; Back</flux:button>
                <flux:button wire:click="nextStep" variant="primary">Next: Checklist Builder &rarr;</flux:button>
            </div>
        </div>
    @endif

    <!-- STEP 4: CHECKLIST BUILDER -->
    @if($step === 4)
        <div class="p-6 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <flux:heading size="lg">Step 4: Checklist Questionnaire Builder</flux:heading>
                    <flux:subheading>{{ count($questions) }} questions currently configured</flux:subheading>
                </div>

                <div class="flex items-center space-x-2">
                    <flux:button wire:click="openAiModal" variant="filled" icon="sparkles" class="text-amber-600 dark:text-amber-400">
                        Generate with AI
                    </flux:button>
                    <flux:button wire:click="addQuestion" variant="primary" icon="plus">
                        Add Question
                    </flux:button>
                </div>
            </div>

            @error('questions')
                <div class="p-3 bg-rose-50 dark:bg-rose-950/50 text-rose-800 dark:text-rose-200 text-sm rounded-xl border border-rose-300 dark:border-rose-800">
                    {{ $message }}
                </div>
            @enderror

            <!-- Questions list -->
            <div class="space-y-4">
                @forelse($questions as $idx => $q)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3 relative">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200">
                                    #{{ $idx + 1 }}
                                </span>
                                @if(($q['source'] ?? '') === 'ai')
                                    <flux:badge size="sm" color="amber">AI Generated</flux:badge>
                                @endif
                            </div>

                            <div class="flex items-center space-x-1">
                                <flux:button wire:click="moveQuestionUp({{ $idx }})" size="sm" variant="ghost" icon="arrow-up" aria-label="Move question up" :disabled="$idx === 0" />
                                <flux:button wire:click="moveQuestionDown({{ $idx }})" size="sm" variant="ghost" icon="arrow-down" aria-label="Move question down" :disabled="$idx === count($questions) - 1" />
                                <flux:button wire:click="removeQuestion({{ $idx }})" size="sm" variant="danger" icon="trash" aria-label="Delete question" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div class="sm:col-span-3">
                                <flux:input wire:model="questions.{{ $idx }}.question" label="Question Text *" placeholder="What specific requirement must the auditor verify?" required />
                            </div>
                            <div>
                                <flux:select wire:model.live="questions.{{ $idx }}.type" label="Answer Type">
                                    <flux:select.option value="radio">Radio Options</flux:select.option>
                                    <flux:select.option value="checkbox">Multi Checkbox</flux:select.option>
                                    <flux:select.option value="text">Narrative Text</flux:select.option>
                                </flux:select>
                            </div>
                        </div>

                        <!-- Options for Radio or Checkbox -->
                        @if(in_array($q['type'], ['radio', 'checkbox']))
                            <div class="p-3 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-semibold text-zinc-600 dark:text-zinc-400">Response Choices:</span>
                                    <flux:button wire:click="addOption({{ $idx }})" size="xs" variant="ghost">+ Add Option</flux:button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($q['options'] ?? [] as $optIdx => $optVal)
                                        <div class="flex items-center space-x-1 bg-zinc-100 dark:bg-zinc-700/60 px-2 py-1 rounded-md text-xs">
                                            <input type="text" wire:model="questions.{{ $idx }}.options.{{ $optIdx }}" class="bg-transparent border-0 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none w-28">
                                            <button type="button" wire:click="removeOption({{ $idx }}, {{ $optIdx }})" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <flux:input wire:model="questions.{{ $idx }}.requirement_ref" label="Regulatory Reference" placeholder="e.g. Nig.CARs Part 8.4.1.2; ICAO Doc 9859" />
                            <flux:input wire:model="questions.{{ $idx }}.guidance" label="Auditor Inspection Guidance" placeholder="Specific sampling tips, documents to examine, or physical clues..." />
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-zinc-500 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                        No questions added yet. Click "Add Question" or "Generate with AI" above.
                    </div>
                @endforelse
            </div>

            <div class="flex justify-between pt-4">
                <flux:button wire:click="prevStep" variant="ghost">&larr; Back</flux:button>
                <flux:button wire:click="nextStep" variant="primary">Next: Review & Save &rarr;</flux:button>
            </div>
        </div>
    @endif

    <!-- STEP 5: REVIEW & SAVE -->
    @if($step === 5)
        <div class="p-6 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 space-y-6">
            <flux:heading size="lg">Step 5: Review & Activation</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div>
                    <span class="text-xs text-zinc-500 uppercase font-semibold">Process Name:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100 text-base">{{ $name }}</div>
                    @if($description)
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ $description }}</p>
                    @endif
                </div>

                <div>
                    <span class="text-xs text-zinc-500 uppercase font-semibold">Schedule & Type:</span>
                    <div class="font-bold text-zinc-900 dark:text-zinc-100">
                        {{ ucfirst($type) }} {{ $type === 'routine' ? '(' . ucfirst($frequency) . ')' : '' }}
                    </div>
                    @if($type === 'routine' && $first_due_date)
                        <div class="text-xs text-zinc-500 mt-1">First Due: {{ Carbon::parse($first_due_date)->format('M d, Y') }}</div>
                    @endif
                </div>

                <div>
                    <span class="text-xs text-zinc-500 uppercase font-semibold">Linked Quality Documents:</span>
                    <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-1">
                        {{ count($selectedDocumentIds) }} document(s) attached
                    </div>
                </div>

                <div>
                    <span class="text-xs text-zinc-500 uppercase font-semibold">Checklist Items:</span>
                    <div class="font-bold text-emerald-600 dark:text-emerald-400 text-base mt-1">
                        {{ count($questions) }} questions configured
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="prevStep" variant="ghost">&larr; Back</flux:button>
                <div class="flex space-x-2">
                    <flux:button wire:click="save('draft')" variant="ghost">Save as Draft</flux:button>
                    <flux:button wire:click="save('active')" variant="primary" icon="check">Save & Activate</flux:button>
                </div>
            </div>
        </div>
    @endif

    <!-- AI Generation Modal -->
    <flux:modal wire:model="showAiModal" class="md:w-[28rem] space-y-4">
        @php
            $isLiveAi = app(\App\Contracts\AiService::class) instanceof \App\Services\GeminiAiService;
            $attachedDocs = \App\Models\Document::whereIn('id', $selectedDocumentIds)->get();
        @endphp

        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center space-x-2">
                    <flux:heading size="lg">Generate Checklist with AI</flux:heading>
                    @if($isLiveAi)
                        <flux:badge size="sm" color="emerald">Live AI</flux:badge>
                    @else
                        <flux:badge size="sm" color="zinc">Demo AI</flux:badge>
                    @endif
                </div>
                <flux:subheading>Generate draft aviation QA verification questions</flux:subheading>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <flux:select wire:model="aiDocumentId" label="Source Document">
                    <flux:select.option value="">None, use scope only</flux:select.option>
                    @foreach($attachedDocs as $d)
                        <flux:select.option value="{{ $d->id }}">{{ $d->title }} ({{ strtoupper(pathinfo($d->file_path, PATHINFO_EXTENSION)) }})</flux:select.option>
                    @endforeach
                </flux:select>
                @if(empty($selectedDocumentIds))
                    <p class="text-[11px] text-zinc-500 mt-1 italic">Tip: Link documents in Step 2 to use them as reference standards here.</p>
                @endif
            </div>

            <flux:input wire:model="aiScope" label="Scope / Objective *" placeholder="e.g. Ramp safety, fuel bowser checks..." required />
            <flux:input wire:model="aiCount" type="number" min="1" max="20" label="Number of Questions" required />

            <div wire:loading wire:target="generateWithAi" class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs text-emerald-800 dark:text-emerald-300 flex items-center space-x-2">
                <svg class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="font-medium">Reading document and drafting questions…</span>
            </div>

            <div class="flex justify-end space-x-2 pt-2">
                <flux:button wire:click="$set('showAiModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="generateWithAi" variant="primary" icon="sparkles" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generateWithAi">Generate Questions</span>
                    <span wire:loading wire:target="generateWithAi">Drafting...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
