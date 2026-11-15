<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'directorate_id',
        'department_id',
        'unit_id',
        'type',
        'frequency',
        'next_due_at',
        'created_by',
        'status',
    ];

    protected $casts = [
        'next_due_at' => 'date',
    ];

    public function directorate()
    {
        return $this->belongsTo(Directorate::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->belongsToMany(Document::class, 'document_process');
    }

    public function questions()
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('order');
    }
}
