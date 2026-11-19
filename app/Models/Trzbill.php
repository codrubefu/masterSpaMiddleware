<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trzbill extends Model
{
    use \App\Models\Concerns\UsesScopeIdentity;
    protected $table = 'trzbill';
    protected $primaryKey = 'nrbill';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';
    protected $guarded = ['nrbill'];
}
