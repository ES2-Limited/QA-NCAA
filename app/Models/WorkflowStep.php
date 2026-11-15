<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'order',
        'name',
        'role',
        'action_status',
        'can_return',
        'required',
        'is_final',
    ];

    protected $casts = [
        'order' => 'integer',
        'can_return' => 'boolean',
        'required' => 'boolean',
        'is_final' => 'boolean',
    ];

    /**
     * Scope query to active ordered steps.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('id', 'asc');
    }
}
