<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;

class UToken extends Model
{
    use UuidModel;

    protected $table = 'tb_2001#';
    protected $keyType = 'string';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'token'
    ];

    public function login()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }
}
