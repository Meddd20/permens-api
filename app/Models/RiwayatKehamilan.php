<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;

class RiwayatKehamilan extends Model
{
    use UuidModel;

    protected $table = 'tb_riwayat_kehamilan';

    protected $fillable = [
        'user_id',
        'status',
        'haid_terakhir',
        'kehamilan_awal',
        'kehamilan_akhir',
        'keterangan'
    ];

    public function login()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }
}
