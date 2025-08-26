<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gest extends Model
{
    protected $table = 'gest';
    protected $primaryKey = 'idgest';
    public $timestamps = false;

    protected $fillable = [
        'idfirma',
        'nrgest',
        'nrdep',
        'retail',
        'dengest',
        'numegest',
        'tel',
        'telgest',
        'gestprod',
        'gestserv',
        'gestmp',
        'gestobinv',
        'gestmf',
        'data',
        'compid',
        'idgest',
        'nrf',
        'nrnir',
        'nraviz',
        'nrbf',
        'nrbc',
        'nrpo',
        'nrfbf',
        'virtual',
        'idctgisis',
        'idconsumisis',
        'idrevc',
        'gestfact',
        'transfer',
        'datarapgest',
        'soldrapgest',
        'id_isis',
    ];
}
