<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AuditAnswer extends Model
{
    protected $fillable = [
        'audit_id',
        'question_key',
        'value',
        'comment',
        'lat',
        'lng',
        'location_accuracy',
        'answered_at',
        'answered_by',
    ];

    protected $casts = [
        'value' => 'json',
        'answered_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
        'location_accuracy' => 'float',
        'question_key' => 'integer',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
