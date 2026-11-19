<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gencard extends Model
{
    protected $table = 'gencard';
    protected $primaryKey = 'idgencard';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guarded = ['idgencard'];
}
