<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditResponse extends Model
{
    protected $fillable = [
        'audit_id',
        'checklist_item_id',
        'status',
        'notes',
        'evidence_path',
        'auditor_id',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function checklistItem()
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_item_id');
    }

    public function question()
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_item_id');
    }

    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function finding()
    {
        return $this->hasOne(Finding::class);
    }
}
