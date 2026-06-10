<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'assessment_round_id',
        'user_id',
        'competency_id',
        'score',
        'note',
        'status',
        'last_draft_saved_at',
    ];
}
