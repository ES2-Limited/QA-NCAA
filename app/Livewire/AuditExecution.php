<?php

namespace App\Livewire;

use App\Models\Audit;
use App\Models\AuditResponse;
use App\Services\AiServiceInterface;
use Livewire\Component;
use Livewire\WithFileUploads;

class AuditExecution extends Component
{
    use WithFileUploads;

    public Audit $audit;

    public $activeTab = 'checklist'; // 'checklist' | 'findings' | 'summary'

    // Real-time answers keyed by checklist_item_id
    public $responses = [];

    public $notes = [];

    public $newPhoto;

    public $uploadingItemId = null;

    public function mount(Audit $audit)
    {
        $this->audit = $audit->load(['checklist.items', 'directorate', 'findings.cap']);

        // Initialize state
        foreach ($this->audit->checklist->items ?? [] as $item) {
            $existing = AuditResponse::where('audit_id', $this->audit->id)
                ->where('checklist_item_id', $item->id)
                ->first();

            if ($existing) {
                $this->responses[$item->id] = $existing->status;
                $this->notes[$item->id] = $existing->notes;
            } else {
                $this->responses[$item->id] = 'compliant';
                $this->notes[$item->id] = '';
            }
        }
    }

    public function updateResponse($itemId, $status)
    {
        $this->responses[$itemId] = $status;

        AuditResponse::updateOrCreate(
            [
                'audit_id' => $this->audit->id,
                'checklist_item_id' => $itemId,
            ],
            [
                'status' => $status,
                'notes' => $this->notes[$itemId] ?? null,
                'auditor_id' => auth()->id() ?? 1,
            ]
        );

        if ($this->audit->status === 'scheduled') {
            $this->audit->update(['status' => 'in_progress']);
        }
    }

    public function saveNotes($itemId)
    {
        AuditResponse::updateOrCreate(
            [
                'audit_id' => $this->audit->id,
                'checklist_item_id' => $itemId,
            ],
            [
                'notes' => $this->notes[$itemId] ?? null,
                'auditor_id' => auth()->id() ?? 1,
            ]
        );

        session()->flash('saved_'.$itemId, 'Item notes saved');
    }

    public function generateAiExecutiveSummary(AiServiceInterface $ai)
    {
        $crit = $this->audit->findings()->where('severity', 'critical')->count();
        $maj = $this->audit->findings()->where('severity', 'major')->count();
        $min = $this->audit->findings()->where('severity', 'minor')->count();

        $summary = $ai->generateExecutiveSummary(
            $this->audit->title,
            $this->audit->directorate ? $this->audit->directorate->name : 'NCAA Target',
            $this->audit->compliance_score,
            ['critical' => $crit, 'major' => $maj, 'minor' => $min]
        );

        $this->audit->update([
            'ai_executive_summary' => $summary,
            'status' => 'completed',
            'end_date' => now(),
        ]);

        session()->flash('message', 'AI Executive Summary generated & Audit closed successfully.');
    }

    public function render()
    {
        return view('livewire.audit-execution')
            ->layout('layouts.auditor', ['title' => 'Audit Execution: '.$this->audit->reference_number]);
    }
}
