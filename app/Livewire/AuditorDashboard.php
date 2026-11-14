<?php

namespace App\Livewire;

use App\Models\Audit;
use Livewire\Component;

class AuditorDashboard extends Component
{
    public $filter = 'all';

    public function render()
    {
        $query = Audit::with(['directorate', 'checklist', 'leadAuditor', 'findings'])
            ->latest('start_date');

        if ($this->filter === 'in_progress') {
            $query->where('status', 'in_progress');
        } elseif ($this->filter === 'scheduled') {
            $query->where('status', 'scheduled');
        } elseif ($this->filter === 'completed') {
            $query->whereIn('status', ['completed', 'closed']);
        }

        $audits = $query->get();

        $stats = [
            'total' => Audit::count(),
            'in_progress' => Audit::where('status', 'in_progress')->count(),
            'scheduled' => Audit::where('status', 'scheduled')->count(),
            'completed' => Audit::whereIn('status', ['completed', 'closed'])->count(),
        ];

        return view('livewire.auditor-dashboard', compact('audits', 'stats'))
            ->layout('layouts.auditor', ['title' => 'NCAA Field Auditor Hub']);
    }
}
