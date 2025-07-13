<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'client';

    public $timestamps = false;
    protected $primaryKey = 'spaid';
}
