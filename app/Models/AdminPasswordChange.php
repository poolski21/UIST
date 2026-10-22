<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPasswordChange extends Model
{
    protected $fillable = [
        'actor_id',
        'target_admin_id',
        'target_email',
        'action',
    ];
}
