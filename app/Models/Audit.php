<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $fillable = [
        'reference_number',
        'title',
        'type',
        'directorate_id',
        'checklist_id',
        'lead_auditor_id',
        'location',
        'start_date',
        'end_date',
        'status',
        'scope',
        'executive_summary',
        'ai_executive_summary',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function directorate()
    {
        return $this->belongsTo(Directorate::class);
    }

    public function checklist()
    {
        return $this->belongsTo(Process::class, 'checklist_id');
    }

    public function process()
    {
        return $this->belongsTo(Process::class, 'checklist_id');
    }

    public function leadAuditor()
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    public function responses()
    {
        return $this->hasMany(AuditResponse::class);
    }

    public function findings()
    {
        return $this->hasMany(Finding::class);
    }

    // Helper to calculate compliance score %
    public function getComplianceScoreAttribute(): int
    {
        $total = $this->responses()->count();
        if ($total === 0) {
            return 100;
        }
        $compliant = $this->responses()->where('status', 'compliant')->count();

        return (int) round(($compliant / $total) * 100);
    }
}
