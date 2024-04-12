<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TB_Defensoria extends Model
{
    use HasFactory;

    //protected $connection = 'pgsql';
    protected $connection = 'etno_mysql';
    protected $table = 'tb_defensoria';
    public $timestamps = false;
}
