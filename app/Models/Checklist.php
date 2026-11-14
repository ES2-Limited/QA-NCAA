<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $fillable = [
        'title',
        'code',
        'category',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('order_index');
    }

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }
}
