<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Verifikasi extends Model
{
    use HasFactory;
    use UuidModel;
    use SoftDeletes;

    protected $table = 'tb_verifikasi';
    protected $keyType = 'string';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    public $incrementing = false;

}
