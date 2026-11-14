<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'directorate_id',
        'name',
        'code',
        'head_of_department',
        'description',
    ];

    public function directorate()
    {
        return $this->belongsTo(Directorate::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
