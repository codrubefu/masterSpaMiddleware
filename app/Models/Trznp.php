<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trznp extends Model
{
    use HasFactory;

    protected $table = 'trznp';
    protected $primaryKey = 'nrnpint';
    public $timestamps = false;

    protected $fillable = [
        'nrnpint',
        'spaid',
        'totalron',
        'tva19',
        'data',
        'compid',
        'idtrzmodp',
        'idfirma',
        'obscui',
        'modp',
        'tipcc',
        'nrtrzcc',
        'nppost',
        'cardid',
        'fapartner',
        'noshow',
        'nrbonf',
        'idrevc',
        'nrfactbf',
        'nrnpspec',
        'nrfact',
        'obs',
        'idrec',
        'idlogin',
        'nrfapartner',
        'prmcod',
        'tipnp',
        'masa',
        'protocol',
        'tip',
        'datac',
        'nrctr',
        'dataactiv',
        'dataend',
        'bridge',
        'databridge',
        'nrnpstorno',
        'descval',
        'nrnpgrup',
        'idrezervarehotel',
        'soldbv',
        'discount',
        'idrezgrup',
        'npgruprez',
        'idcmdweb',
    ];
}
