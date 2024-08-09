<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeratIdealIbuHamil extends Model
{
    use HasFactory;

    protected $table = 'tb_berat_badan_kehamilan';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function login(): BelongsTo {
        return $this->belongsTo(Login::class, 'user_id');
    }

    public function riwayat_kehamilan(): BelongsTo {
        return $this->belongsTo(RiwayatKehamilan::class, 'riwayat_kehamilan_id');
    }
}
