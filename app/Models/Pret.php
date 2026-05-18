<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pret extends Model
{
    use HasFactory;

    protected $table = 'pret';

    protected $fillable = [
        'idfirma',
        'clasa',
        'grupa',
        'art',
        'pret',
        'pretdesc',
        'cf',
        'pretnir',
        'data',
        'compid',
        'idpret',
        'phdatas',
        'phdataf',
        'phst',
        'phfs',
        'idhotel',
        'phtipcamera',
        'phmasa',
        'phadult',
        'phbaby',
        'phc26',
        'phc614',
        'phc1418',
        'prethotel',
        'phspahotel',
        'phsparesort',
        'phmed',
        'phpercam',
        'phspecial',
        'tipcamera',
        'data_start',
        'data_end',
    ];

    public function camerehotel()
    {
        return $this->hasOne(Camerehotel::class, ['tipcamera' => 'tip']);
    }

    public function genprod()
    {
        return $this->belongsTo(Genprod::class, 'art', 'art')
            ->select(['clseq', 'grpeq', 'arteq', 'deseq'])
            ->where('clasa', $this->clasa)
            ->where('grupa', $this->grupa)
            ->first()
            ->toArray()
            ;
    }
}
