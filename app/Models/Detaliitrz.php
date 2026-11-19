<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detaliitrz extends Model
{
    protected $table = 'detaliitrz';
    protected $primaryKey = 'idtrz';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guarded = ['idtrz'];
}
