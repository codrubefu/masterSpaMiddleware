<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'client';
    protected $primaryKey = 'spaid';
    public $timestamps = false;

    protected $fillable = [
        'cnpcui',
        'den',
        'prenume',
        'adresa1',
        'adresa2',
        'oras',
        'judet',
        'tara',
        'reg',
        'cp',
        'tel',
        'fax',
        'email',
        'contact',
        'mobilcontact',
        'emailcontact',
        'banca',
        'iban',
        'dirgen',
        'teldg',
        'emaildg',
        'dircom',
        'teldc',
        'emaildc',
        'pj',
        'datan',
        'varsta',
        'sex',
        'profesie',
        'modp',
        'discount',
        'valuta',
        'religie',
        'obscui',
        'startper',
        'endper',
        'nrc',
        'vat',
        'termenp',
        'limcredit',
        'hotel',
        'camera',
        'atojki',
        'nrvoucher',
        'datacreare',
        'compid',
        'institutia',
        'idfirma',
        'soldcurent',
        'cardid',
        'rulajt',
        'rulajp',
        'rulajs',
        'conthotel',
        'obs',
        'dataaniv',
        'pasaport',
        'tip',
        'origin',
        'clhead',
        'numevip',
        'mobilvip',
        'emailvip',
        'vip',
        'idlevel',
        'puncte',
        'spalssemnat',
        'spalsmktaccept',
        'sursamkt',
        'canalmkt',
        'sizeb',
        'nrvestiar',
        'zonavestiar',
        'poza',
        'angajat',
        'datac',
        'idpartener',
        'emb',
        'activ',
        'exisis',
        'datacodsaga',
        'guvern'
    ];

    protected $casts = [
        'datan' => 'date',
        'startper' => 'date',
        'endper' => 'date',
        'datacreare' => 'datetime',
        'dataaniv' => 'date',
        'datac' => 'datetime',
        'datacodsaga' => 'date',
        'varsta' => 'integer',
        'discount' => 'decimal:2',
        'limcredit' => 'decimal:2',
        'soldcurent' => 'decimal:2',
        'rulajt' => 'decimal:2',
        'rulajp' => 'decimal:2',
        'rulajs' => 'decimal:2',
        'puncte' => 'integer',
        'vip' => 'boolean',
        'spalssemnat' => 'boolean',
        'spalsmktaccept' => 'boolean',
        'angajat' => 'boolean',
        'activ' => 'boolean',
        'exisis' => 'boolean',
        'guvern' => 'boolean'
    ];

    // Relationships
    public function rezervari()
    {
        return $this->hasMany(Rezervarehotel::class, 'idcl');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('activ', 1);
    }

    public function scopeVip($query)
    {
        return $query->where('vip', 1);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByPhone($query, $phone)
    {
        return $query->where('tel', $phone)
                    ->orWhere('mobilcontact', $phone);
    }

    public function scopeByName($query, $name)
    {
        return $query->where('den', 'like', "%{$name}%")
                    ->orWhere('prenume', 'like', "%{$name}%");
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return trim($this->prenume . ' ' . $this->den);
    }

    public function getIsVipAttribute()
    {
        return (bool) $this->vip;
    }
}
