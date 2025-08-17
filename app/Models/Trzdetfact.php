<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trzdetfact extends Model
{
    protected $table = 'trzdetfact';
    public $timestamps = false;

    protected $fillable = [
        'idfirma',
        'nrfact',
        'idcl',
        'clasa',
        'grupa',
        'art',
        'cant',
        'cantf',
        'pretueur',
        'preturon',
        'redabs',
        'redproc',
        'valoare',
        'tva',
        'data',
        'compid',
        'idtrzf',
        'idpers',
        'preturondisc',
        'cotatva',
        'valoare2',
        'tva2',
    ];
}
