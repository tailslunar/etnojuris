<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TB_Sentenca extends Model
{
    use HasFactory;

    protected $connection = 'etno_mysql';
    protected $table = 'tb_sentenca';
    public $timestamps = false;
}
