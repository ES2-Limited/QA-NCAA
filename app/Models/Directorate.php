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

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }
}
