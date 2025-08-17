<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trzdetnp extends Model
{
    use HasFactory;

    protected $table = 'trzdetnp';
    protected $primaryKey = 'idtrzf';
    public $timestamps = false;

    protected $fillable = [
        'idtrzf',
        'nrnp',
        'spaid',
        'art',
        'cant',
        'pretueur',
        'preturon',
        'valoare',
        'data',
        'compid',
        'idpers',
        'redproc',
        'inchidzi',
        'idfirma',
        'reg',
        'cardid',
        'redabn',
        'pretfaradisc',
        'idprog',
        'idtrz',
        'valuta',
        'cursv',
        'datac',
        'dataactiv',
        'nrvestiar',
        'codb',
        'cotatva',
        'nrvestiar2',
        'codb2',
        'genconsum',
        'reinnoire',
        'cardb',
        'tipf',
        'idcldet',
        'idtrzcreditcard',
        'idrezervarehotel',
    ];
}
