<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trzdetbill extends Model
{
    protected $table = 'trzdetbill';
    protected $primaryKey = 'idtrzf';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guarded = ['idtrzf'];
}
