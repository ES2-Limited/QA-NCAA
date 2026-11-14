<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Directorate extends Model
{
    protected $fillable = [
        'name',
        'code',
        'head_of_directorate',
        'email',
        'description',
    ];

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }
}
