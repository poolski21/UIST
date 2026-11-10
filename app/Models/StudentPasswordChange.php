<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPasswordChange extends Model
{
    protected $fillable = [
        'actor_id',
        'target_student_id',
        'action',
    ];
}
