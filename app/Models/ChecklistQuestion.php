<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_id',
        'order',
        'question',
        'type',
        'options',
        'requirement_ref',
        'guidance',
        'source',
    ];

    protected $casts = [
        'options' => 'array',
        'order' => 'integer',
    ];

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    public function getItemCodeAttribute(): string
    {
        return 'Q-'.str_pad((string) $this->order, 2, '0', STR_PAD_LEFT);
    }

    public function getRequirementAttribute(): string
    {
        return $this->question;
    }

    public function getRegulatoryReferenceAttribute(): ?string
    {
        return $this->requirement_ref;
    }
}
