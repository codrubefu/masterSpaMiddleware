<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camerehotel extends Model
{
    use HasFactory;

    protected $table = 'camerehotel';
    protected $primaryKey = 'idcamerehotel';
    public $timestamps = false;

    protected $fillable = [
        'nr',
        'virtual',
        'idlabel',
        'tip',
        'tiplung',
        'pagina',
        'idhotel',
        'etajresel',
        'nrcamresel',
        'etajhk',
        'idtabletahk',
        'locknr',
        'adultMax',
        'kidMax',
        'babyBed',
        'bed'
    ];

    protected $casts = [
        'virtual' => 'boolean',
        'idlabel' => 'integer',
        'pagina' => 'integer',
        'idhotel' => 'integer',
        'etajresel' => 'integer',
        'nrcamresel' => 'integer',
        'etajhk' => 'integer',
        'idtabletahk' => 'integer',
        'locknr' => 'integer',
        'adultMax' => 'integer',
        'kidMax' => 'integer',
        'babyBed' => 'integer',
        'bed' => 'integer'
    ];

    // Relationships
    public function rezervari()
    {
        return $this->hasMany(Rezervarehotel::class, 'camera', 'nr');
    }

    public function pret()
    {
        return $this->hasMany(Pret::class, 'tipcamera', 'tip');
    }

    // Scopes
    public function scopeByHotel($query, $hotelId)
    {
        return $query->where('idhotel', $hotelId);
    }

    public function scopeByFloor($query, $floor)
    {
        return $query->where('etajresel', $floor);
    }

    public function scopeMinCapacity($query, $adults, $kids = 0)
    {
        return $query->where('adultMax', '>=', $adults)
                    ->where('kidMax', '>=', $kids);
    }

    public function scopeTotalCapacity($query, $totalPeople)
    {
        return $query->whereRaw('adultMax + kidMax >= ?', [$totalPeople]);
    }

    public function scopeVirtual($query, $isVirtual = true)
    {
        return $query->where('virtual', $isVirtual);
    }

    public function scopeWithBabyBed($query)
    {
        return $query->where('babyBed', '>', 0);
    }

    // Accessors
    public function getTotalCapacityAttribute()
    {
        return $this->adultMax + $this->kidMax;
    }

    public function getIsVirtualAttribute()
    {
        return (bool) $this->virtual;
    }

    public function getHasBabyBedAttribute()
    {
        return $this->babyBed > 0;
    }
}
