<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CorrectiveActionPlan extends Model
{
    protected $table = 'corrective_action_plans';

    protected $fillable = [
        'ncr_id',
        'finding_id', // legacy
        'description',
        'proposed_action', // legacy alias
        'root_cause',
        'root_cause_analysis', // legacy alias
        'target_date',
        'target_completion_date', // legacy alias
        'submitted_by',
        'responsible_person',
        'status', // submitted, accepted, rejected
        'reviewer_comment',
        'reviewed_by',
        'reviewed_at',
        'verification_notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'target_completion_date' => 'date',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function ncr()
    {
        return $this->belongsTo(Finding::class, 'ncr_id')
            ->withDefault(function () {
                return $this->belongsTo(Finding::class, 'finding_id')->first();
            });
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
