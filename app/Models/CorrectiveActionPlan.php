<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrectiveActionPlan extends Model
{
    protected $fillable = [
        'finding_id',
        'proposed_action',
        'root_cause_analysis',
        'responsible_person',
        'target_completion_date',
        'status',
        'verification_notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'target_completion_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function finding()
    {
        return $this->belongsTo(Finding::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
