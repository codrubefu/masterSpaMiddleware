<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trzcard extends Model
{
    protected $table = 'trzcard';
    protected $primaryKey = 'idtrzcard';
    public $timestamps = false;

    protected $fillable = [
        'idclc',
        'idclb',
        'mesaj',
        'clasa',
        'grupa',
        'art',
        'datae',
        'dataexp',
        'pret',
        'datac',
        'utilizator',
        'idfirma',
        'idpers',
        'upc',
        'nrnp',
        'obs',
        'reinnoit',
        'nrren',
        'nrctr',
        'discount',
        'consumat',
        'dataconsumat',
        'nrnpgrup',
    ];

    protected $casts = [
        'idtrzcard' => 'integer',
        'idclc' => 'integer',
        'idclb' => 'integer',
        'pret' => 'float',
        'idfirma' => 'integer',
        'idpers' => 'integer',
        'nrnp' => 'integer',
        'reinnoit' => 'boolean',
        'nrren' => 'integer',
        'nrctr' => 'integer',
        'discount' => 'float',
        'consumat' => 'boolean',
        'nrnpgrup' => 'integer',
    ];
}
