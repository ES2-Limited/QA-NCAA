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
}
