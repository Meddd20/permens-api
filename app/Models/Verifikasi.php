<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Verifikasi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tb_verifikasi';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
}
