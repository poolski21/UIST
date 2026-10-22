<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConnexionLog extends Model
{
    protected $table = 'connexions_log';

    protected $fillable = [
        'user_id', 'matricule', 'type_connexion', 'reussie', 'adresse_ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['reussie' => 'boolean'];
    }
}
