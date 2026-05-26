<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'role_id',
        'role_key', 
        'name_th',
        'name_en',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'role_id', 'role_id');
    }
}