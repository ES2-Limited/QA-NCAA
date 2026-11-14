<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = [
        'audit_id',
        'number',
        'issued_at',
        'issued_by',
        'file_path',
        'verify_token',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
