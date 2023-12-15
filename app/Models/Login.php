<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Login extends Authenticatable
{
    use UuidModel;
    use SoftDeletes;

    protected $table = 'tb_1001#';
    protected $guard = 'login';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'status',
        'role',
        'nama',
        'tanggal_lahir',
        'is_pregnant',
        'email',
        'pwd',
    ];

    protected $hidden = [
        'pwd'
    ];

    public function getAuthPassword() {
        return $this->pwd;
    }
}
