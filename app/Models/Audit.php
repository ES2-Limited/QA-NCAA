<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $fillable = [
        'reference_number',
        'process_id',
        'title',
        'scheduled_for',
        'type',
        'directorate_id',
        'checklist_id',
        'lead_auditor_id',
        'reviewer_id',
        'created_by',
        'location',
        'start_date',
        'end_date',
        'status',
        'submitted_at',
        'questions_snapshot',
        'scope',
        'ai_summary',
        'executive_summary',
        'ai_executive_summary',
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'submitted_at' => 'datetime',
        'questions_snapshot' => 'array',
    ];

    public function process()
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function directorate()
    {
        return $this->belongsTo(Directorate::class);
    }

    public function leadAuditor()
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function auditors()
    {
        return $this->belongsToMany(User::class, 'audit_user')->withTimestamps();
    }

    public function answers()
    {
        return $this->hasMany(AuditAnswer::class);
    }

    public function responses()
    {
        return $this->hasMany(AuditResponse::class);
    }

    public function findings()
    {
        return $this->hasMany(Finding::class);
    }

    public function approvals()
    {
        return $this->hasMany(AuditApproval::class)->orderBy('created_at', 'asc');
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class);
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
