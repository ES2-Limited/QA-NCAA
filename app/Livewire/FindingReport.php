<?php

namespace App\Livewire;

use App\Models\Audit;
use App\Models\Finding;
use App\Services\AiServiceInterface;
use Livewire\Component;

class FindingReport extends Component
{
    public Audit $audit;

    public $findingNumber;

    public $title = '';

    public $severity = 'major';

    public $condition = '';

    public $criteria = '';

    public $cause = '';

    public $effect = '';

    public $recommendation = '';

    public $dueDate;

    public $aiAnalysis = null;

    public $isAnalyzing = false;

    public function mount(Audit $audit)
    {
        $this->audit = $audit;
        $this->findingNumber = 'FND-'.date('Y').'-'.rand(100, 999);
        $this->dueDate = now()->addDays(21)->format('Y-m-d');
        $this->criteria = 'Nigeria Civil Aviation Regulations (Nig.CARs) Part 8.4 & ICAO Annex 6';
    }

    public function triggerAiAnalysis(AiServiceInterface $ai)
    {
        $this->validate([
            'title' => 'required|min:5',
            'condition' => 'required|min:10',
        ]);

        $this->isAnalyzing = true;
        $this->aiAnalysis = $ai->analyzeFinding($this->title, $this->condition, $this->criteria);

        if (! empty($this->aiAnalysis['severity_recommendation'])) {
            $this->severity = $this->aiAnalysis['severity_recommendation'];
        }
        if (empty($this->cause) && ! empty($this->aiAnalysis['root_cause_analysis'])) {
            $this->cause = $this->aiAnalysis['root_cause_analysis'];
        }
        if (empty($this->effect) && ! empty($this->aiAnalysis['safety_impact'])) {
            $this->effect = $this->aiAnalysis['safety_impact'];
        }
        if (empty($this->recommendation) && ! empty($this->aiAnalysis['recommended_actions'])) {
            $this->recommendation = implode("\n- ", $this->aiAnalysis['recommended_actions']);
            $this->recommendation = '- '.$this->recommendation;
        }

        $this->isAnalyzing = false;
        session()->flash('ai_success', 'AI Root-cause & severity guidance generated.');
    }

    public function saveFinding()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'severity' => 'required',
            'condition' => 'required|string',
            'criteria' => 'required|string',
            'dueDate' => 'required|date',
        ]);

        $finding = Finding::create([
            'audit_id' => $this->audit->id,
            'finding_number' => $this->findingNumber,
            'title' => $this->title,
            'severity' => $this->severity,
            'condition' => $this->condition,
            'criteria' => $this->criteria,
            'cause' => $this->cause,
            'effect' => $this->effect,
            'recommendation' => $this->recommendation,
            'status' => 'open',
            'due_date' => $this->dueDate,
            'ai_analysis' => $this->aiAnalysis,
        ]);

        // Auto-seed draft CAP for quick demo review
        $finding->cap()->create([
            'responsible_person' => $this->audit->directorate ? $this->audit->directorate->head_of_directorate : 'Station Ops Lead',
            'target_completion_date' => $this->dueDate,
            'root_cause_analysis' => $this->cause ?: 'Procedures not formally documented or verified during shift handovers.',
            'proposed_action' => $this->recommendation ?: 'Establish documented sign-off workflow and retrain shift supervisors.',
            'status' => 'pending_approval',
        ]);

        return redirect()->route('auditor.execute', $this->audit->id)
            ->with('message', 'Finding '.$this->findingNumber.' filed with AI risk assessment.');
    }

    public function render()
    {
        return view('livewire.finding-report')
            ->layout('layouts.auditor', ['title' => 'Log Audit Finding - '.$this->audit->reference_number]);
    }
}
