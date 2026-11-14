<?php

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Document;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Title;

new #[Title('NCAA Document Library')] class extends Component {
    use WithFileUploads;

    public $search = '';
    public $filterDirectorate = '';
    public $filterDepartment = '';
    public $filterUnit = '';

    // Upload & Edit Form State
    public $title = '';
    public $description = '';
    public $formDirectorate = '';
    public $formDepartment = '';
    public $unit_id = '';
    public $file;
    public $editingId = null;
    public $showUploadModal = false;
    public $showEditModal = false;

    public function updatedFilterDirectorate()
    {
        $this->filterDepartment = '';
        $this->filterUnit = '';
    }

    public function updatedFilterDepartment()
    {
        $this->filterUnit = '';
    }

    public function updatedFormDirectorate()
    {
        $this->formDepartment = '';
        $this->unit_id = '';
    }

    public function updatedFormDepartment()
    {
        $this->unit_id = '';
    }

    public function openUploadModal()
    {
        $this->reset(['title', 'description', 'formDirectorate', 'formDepartment', 'unit_id', 'file', 'editingId']);
        $this->showUploadModal = true;
    }

    public function openEditModal($id)
    {
        $doc = Document::with('unit.department.directorate')->findOrFail($id);
        $this->editingId = $doc->id;
        $this->title = $doc->title;
        $this->description = $doc->description;
        $this->unit_id = $doc->unit_id ?? '';

        if ($doc->unit) {
            $this->formDirectorate = $doc->unit->department->directorate_id ?? '';
            $this->formDepartment = $doc->unit->department_id ?? '';
        } else {
            $this->formDirectorate = '';
            $this->formDepartment = '';
        }

        $this->showEditModal = true;
    }

    public function uploadDocument()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_id' => 'nullable|exists:units,id',
            'file' => 'required|file|mimes:pdf,docx|max:20480', // 20 MB max
        ], [
            'file.mimes' => 'Only PDF or DOCX files are permitted for compliance archives.',
            'file.max' => 'The document must not exceed 20 MB.',
        ]);

        $originalName = $this->file->getClientOriginalName();
        $mimeType = $this->file->getMimeType();
        $size = $this->file->getSize();

        // Store file on public disk under documents/
        $path = $this->file->store('documents', 'public');

        Document::create([
            'title' => $this->title,
            'description' => $this->description,
            'file_path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
            'unit_id' => $this->unit_id ?: null,
            'uploaded_by' => auth()->id() ?? 1,
        ]);

        $this->showUploadModal = false;
        $this->reset(['title', 'description', 'formDirectorate', 'formDepartment', 'unit_id', 'file']);
        session()->flash('message', 'Document uploaded successfully to NCAA Library.');
    }

    public function updateDocument()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        $doc = Document::findOrFail($this->editingId);
        $doc->update([
            'title' => $this->title,
            'description' => $this->description,
            'unit_id' => $this->unit_id ?: null,
        ]);

        $this->showEditModal = false;
        $this->reset(['title', 'description', 'formDirectorate', 'formDepartment', 'unit_id', 'editingId']);
        session()->flash('message', 'Document metadata updated.');
    }

    public function deleteDocument($id)
    {
        $doc = Document::findOrFail($id);

        if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }

        $doc->delete();
        session()->flash('message', 'Document removed and file purged.');
    }

    public function formatBytes($bytes, $precision = 1)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function with()
    {
        $query = Document::with(['uploader', 'unit.department.directorate'])->latest();

        if (!empty($this->search)) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        if (!empty($this->filterUnit)) {
            $query->where('unit_id', $this->filterUnit);
        } elseif (!empty($this->filterDepartment)) {
            $query->whereHas('unit', fn ($q) => $q->where('department_id', $this->filterDepartment));
        } elseif (!empty($this->filterDirectorate)) {
            $query->whereHas('unit.department', fn ($q) => $q->where('directorate_id', $this->filterDirectorate));
        }

        $directorates = Directorate::orderBy('name')->get();

        $filterDepartments = !empty($this->filterDirectorate)
            ? Department::where('directorate_id', $this->filterDirectorate)->orderBy('name')->get()
            : collect();

        $filterUnits = !empty($this->filterDepartment)
            ? Unit::where('department_id', $this->filterDepartment)->orderBy('name')->get()
            : collect();

        $formDepartments = !empty($this->formDirectorate)
            ? Department::where('directorate_id', $this->formDirectorate)->orderBy('name')->get()
            : collect();

        $formUnits = !empty($this->formDepartment)
            ? Unit::where('department_id', $this->formDepartment)->orderBy('name')->get()
            : collect();

        return [
            'documents' => $query->get(),
            'directorates' => $directorates,
            'filterDepartments' => $filterDepartments,
            'filterUnits' => $filterUnits,
            'formDepartments' => $formDepartments,
            'formUnits' => $formUnits,
        ];
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout :heading="__('NCAA Document Library')" :subheading="__('Central quality manuals, audit checklists, regulations, and operational procedure documents')">

        @if (session()->has('message'))
            <div class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <!-- Filter & Search Controls Header -->
        <div class="mb-6 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="w-full sm:w-80">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Search documents by title..." icon="magnifying-glass" />
                </div>
                <flux:button wire:click="openUploadModal" variant="primary" icon="arrow-up-tray">
                    {{ __('Upload Document') }}
                </flux:button>
            </div>

            <!-- Cascading Hierarchy Filter -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:select wire:model.live="filterDirectorate" placeholder="All Directorates">
                    <flux:select.option value="">All Directorates</flux:select.option>
                    @foreach($directorates as $dir)
                        <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} - {{ $dir->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="filterDepartment" placeholder="All Departments" :disabled="empty($filterDirectorate)">
                    <flux:select.option value="">All Departments</flux:select.option>
                    @foreach($filterDepartments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="filterUnit" placeholder="All Units" :disabled="empty($filterDepartment)">
                    <flux:select.option value="">All Units</flux:select.option>
                    @foreach($filterUnits as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <!-- Documents Table -->
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs font-semibold uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Document Title</th>
                        <th class="px-4 py-3">Assigned Unit / Dept</th>
                        <th class="px-4 py-3">Uploaded By</th>
                        <th class="px-4 py-3">Size</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-800">
                    @forelse($documents as $doc)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-4 py-3">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center space-x-1.5">
                                    <span class="text-emerald-600 dark:text-emerald-400 font-mono text-xs uppercase px-1.5 py-0.5 bg-emerald-50 dark:bg-emerald-950/60 rounded border border-emerald-200 dark:border-emerald-800">
                                        {{ str_contains($doc->mime_type, 'pdf') ? 'PDF' : 'DOCX' }}
                                    </span>
                                    <span>{{ $doc->title }}</span>
                                </div>
                                <div class="text-xs text-zinc-500 mt-0.5 truncate max-w-sm">{{ $doc->original_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-700 dark:text-zinc-300">
                                @if($doc->unit)
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $doc->unit->name }}</div>
                                    <div class="text-zinc-500">{{ $doc->unit->department->code ?? '' }} ({{ $doc->unit->department->directorate->code ?? '' }})</div>
                                @else
                                    <span class="text-zinc-400 italic">General / Unassigned</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $doc->uploader->name ?? 'System' }}</div>
                                <div class="text-[11px] text-zinc-400">{{ $doc->uploader->email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $this->formatBytes($doc->size) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-500">
                                {{ $doc->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <flux:button href="{{ route('admin.documents.download', $doc->id) }}" size="sm" variant="ghost" icon="arrow-down-tray">
                                    Download
                                </flux:button>
                                <flux:button wire:click="openEditModal({{ $doc->id }})" size="sm" variant="ghost">
                                    Edit
                                </flux:button>
                                <flux:button wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Are you sure you want to permanently delete this document and file?" size="sm" variant="danger">
                                    Delete
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No documents found matching your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Upload Document Modal -->
        <flux:modal wire:model="showUploadModal" class="md:w-[500px] space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Upload Compliance Document') }}</flux:heading>
                <flux:subheading>{{ __('Archive PDF or DOCX file up to 20 MB with organizational assignment') }}</flux:subheading>
            </div>

            <form wire:submit="uploadDocument" class="space-y-4">
                <flux:input wire:model="title" label="Document Title" placeholder="e.g. Flight Safety Inspection Guidelines 2026" required />
                <flux:textarea wire:model="description" label="Summary / Scope" rows="2" placeholder="Brief outline of regulation or manual contents..." />

                <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl space-y-3 border border-zinc-200 dark:border-zinc-700">
                    <flux:label>Optional Unit Assignment</flux:label>
                    <flux:select wire:model.live="formDirectorate" label="Directorate">
                        <flux:select.option value="">None / Authority-wide</flux:select.option>
                        @foreach($directorates as $dir)
                            <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} - {{ $dir->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="formDepartment" label="Department" :disabled="empty($formDirectorate)">
                        <flux:select.option value="">None / Directorate-wide</flux:select.option>
                        @foreach($formDepartments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="unit_id" label="Operational Unit" :disabled="empty($formDepartment)">
                        <flux:select.option value="">None / Department-wide</flux:select.option>
                        @foreach($formUnits as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:input wire:model="file" type="file" label="Select File (PDF or DOCX, max 20 MB)" required />
                    <div wire:loading wire:target="file" class="text-xs text-emerald-600 mt-1 font-medium">
                        Uploading file preview...
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <flux:button wire:click="$set('showUploadModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Upload Document</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Edit Document Metadata Modal -->
        <flux:modal wire:model="showEditModal" class="md:w-[500px] space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Edit Document Metadata') }}</flux:heading>
                <flux:subheading>{{ __('Update title, description, or organizational unit') }}</flux:subheading>
            </div>

            <form wire:submit="updateDocument" class="space-y-4">
                <flux:input wire:model="title" label="Document Title" required />
                <flux:textarea wire:model="description" label="Summary / Scope" rows="2" />

                <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl space-y-3 border border-zinc-200 dark:border-zinc-700">
                    <flux:label>Organizational Assignment</flux:label>
                    <flux:select wire:model.live="formDirectorate" label="Directorate">
                        <flux:select.option value="">None / Authority-wide</flux:select.option>
                        @foreach($directorates as $dir)
                            <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} - {{ $dir->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="formDepartment" label="Department" :disabled="empty($formDirectorate)">
                        <flux:select.option value="">None / Directorate-wide</flux:select.option>
                        @foreach($formDepartments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="unit_id" label="Operational Unit" :disabled="empty($formDepartment)">
                        <flux:select.option value="">None / Department-wide</flux:select.option>
                        @foreach($formUnits as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <flux:button wire:click="$set('showEditModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save Changes</flux:button>
                </div>
            </form>
        </flux:modal>

    </x-pages::admin.layout>
</section>
