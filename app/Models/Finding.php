<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Finding extends Model
{
    protected $table = 'findings';

    protected $fillable = [
        'audit_id',
        'audit_answer_id',
        'reference',
        'finding_number', // legacy alias
        'level',
        'title',
        'description',
        'condition',
        'criteria',
        'requirement_ref',
        'cause',
        'effect',
        'recommendation',
        'assigned_unit_id',
        'raised_by',
        'cap_due_at',
        'due_date',
        'status', // open, cap_submitted, cap_accepted, cap_rejected, closed
        'ai_analysis',
    ];

    protected $casts = [
        'level' => 'integer',
        'cap_due_at' => 'datetime',
        'due_date' => 'date',
        'ai_analysis' => 'array',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function answer()
    {
        return $this->belongsTo(AuditAnswer::class, 'audit_answer_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'assigned_unit_id');
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function cap()
    {
        return $this->hasOne(CorrectiveActionPlan::class, 'ncr_id')
            ->withDefault(function () {
                return $this->hasOne(CorrectiveActionPlan::class, 'finding_id')->first();
            });
    }

    public function caps()
    {
        return $this->hasMany(CorrectiveActionPlan::class, 'ncr_id');
    }

    public function isOverdue(): bool
    {
        return $this->cap_due_at && $this->cap_due_at->isPast() && in_array($this->status, ['open', 'cap_rejected']);
    }
}
