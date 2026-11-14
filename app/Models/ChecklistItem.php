<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $fillable = [
        'checklist_id',
        'item_code',
        'requirement',
        'guidance',
        'regulatory_reference',
        'order_index',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function responses()
    {
        return $this->hasMany(AuditResponse::class);
    }
}
