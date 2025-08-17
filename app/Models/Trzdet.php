<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trzdet extends Model
{
    use HasFactory;

    protected $table = 'trzdet';
    protected $primaryKey = 'idtrzdet';
    public $timestamps = false;

    protected $fillable = [
        'idtrzdet',
        'idfirma',
        'nrdoc',
        'idcl',
        'clasa',
        'grupa',
        'art',
        'cant',
        'pretueur',
        'preturon',
        'valoare',
        'tva',
        'tip',
        'data',
        'compid',
        'idpers',
        'redproc',
        'cardid',
        'redabn',
        'pretfaradisc',
        'modp',
        'idprog',
        'idtrz',
        'idrevc',
        'valuta',
        'cursv',
        'datac',
        'cotatva',
        'reinnoire',
        'cardb',
        'idcldet',
    ];
}
