<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rezervarehotel extends Model
{
    use HasFactory;

    protected $table = 'rezervarehotel';
    protected $primaryKey = 'idrezervarehotel';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';
    protected $fillable = [
        'idcl',
        'idclagentie1',
        'idclagentie2',
        'datas',
        'dataf',
        'camera',
        'tipcamera',
        'obsdatas',
        'obsdataf',
        'nrnopti',
        'status',
        'nradulti',
        'nrcopii',
        'tipmasa',
        'prettipmasa',
        'pachet',
        'pretcamera',
        'pretmasa',
        'pretpachet',
        'pretextra',
        'discount',
        'pretnoapte',
        'total',
        'idfirma',
        'utilizator',
        'idloginuser',
        'data',
        'checkin',
        'datacheckin',
        'checkout',
        'datacheckout',
        'platit',
        'nrnp',
        'idrec',
        'motivdel',
        'sters',
        'datadel',
        'utilizatordel',
        'idlogindel',
        'idhotel',
        'agent',
        'curat',
        'datacurat',
        'tip',
        'idrezgrup',
        'clheadrez'
    ];

    protected $casts = [
        'datas' => 'datetime',
        'dataf' => 'datetime',
        'data' => 'datetime',
        'datacheckin' => 'datetime',
        'datacheckout' => 'datetime',
        'datadel' => 'datetime',
        'datacurat' => 'datetime',
        'prettipmasa' => 'decimal:2',
        'pretcamera' => 'decimal:2',
        'pretmasa' => 'decimal:2',
        'pretpachet' => 'decimal:2',
        'pretextra' => 'decimal:2',
        'discount' => 'decimal:2',
        'pretnoapte' => 'decimal:2',
        'total' => 'decimal:2',
        'platit' => 'decimal:2',
        'checkin' => 'boolean',
        'checkout' => 'boolean',
        'sters' => 'boolean',
        'curat' => 'boolean'
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class, 'idcl');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('sters', 0);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->where(function($q) use ($startDate, $endDate) {
            $q->whereRaw('? < dataf AND ? > datas', [$startDate, $endDate]);
        });
    }

    public function scopeForRoom($query, $roomNumber)
    {
        return $query->where('camera', $roomNumber);
    }
}
