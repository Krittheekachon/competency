<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = ['name', 'workline_id'];

    public function workline()
    {
        return $this->belongsTo(Workline::class);
    }
}
