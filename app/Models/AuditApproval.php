<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditApproval extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audit_id',
        'user_id',
        'role',
        'action',
        'comment',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
