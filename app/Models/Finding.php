<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Finding extends Model
{
    protected $fillable = [
        'audit_id',
        'audit_response_id',
        'finding_number',
        'title',
        'severity',
        'condition',
        'criteria',
        'cause',
        'effect',
        'recommendation',
        'status',
        'due_date',
        'ai_analysis',
    ];

    protected $casts = [
        'due_date' => 'date',
        'ai_analysis' => 'array',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function auditResponse()
    {
        return $this->belongsTo(AuditResponse::class);
    }

    public function cap()
    {
        return $this->hasOne(CorrectiveActionPlan::class);
    }
}
