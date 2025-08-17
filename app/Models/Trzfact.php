<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trzfact extends Model
{
    protected $table = 'trzfact';
    protected $primaryKey = 'nrfact';
    public $timestamps = false;

    protected $fillable = [
        'nrfact',
        'idfirma',
        'nrfactfisc',
        'nrdep',
        'nrgest',
        'idcl',
        'stotalron',
        'redabs',
        'redproc',
        'tva',
        'cotatva',
        'totalron',
        'sold',
        'itotalron',
        'itotaleur',
        'itotalusd',
        'modp',
        'nrtrzcc',
        'tipcc',
        'tipv',
        'nume',
        'cnp',
        'ciserie',
        'cinr',
        'cipol',
        'auto',
        'nrauto',
        'datafact',
        'datascad',
        'data',
        'compid',
        'tip',
        'nrfactspec',
        'idpers',
        'costtot',
        'curseur',
        'cursusd',
        'com',
        'datasnp',
        'dataenp',
        'tvainc',
        'idrevc',
        'nrnp',
        'anulat',
        'obs',
        'exisis',
    ];
}
