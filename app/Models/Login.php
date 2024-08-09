<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Login extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $table = 'tb_user';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $hidden = [
        'password'
    ];

    public function getAuthPassword() {
        return $this->password;
    }

    public function artikel(): HasMany {
        return $this->hasMany(Artikel::class, 'user_id');
    }

    public function riwayatLog(): HasMany {
        return $this->hasMany(RiwayatLog::class, 'user_id');
    }

    public function komentar(): HasMany {
        return $this->hasMany(Komentar::class, 'user_id');
    }

    public function komentarLike(): HasMany {
        return $this->hasMany(KomentarLike::class, 'user_id');
    }

    public function riwayatKehamilan(): HasMany {
        return $this->hasMany(RiwayatKehamilan::class, 'user_id');
    }

    public function riwayatMens(): HasMany {
        return $this->hasMany(RiwayatMens::class, 'user_id');
    }
}
