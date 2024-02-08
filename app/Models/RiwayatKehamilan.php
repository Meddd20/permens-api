<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;

class RiwayatKehamilan extends Model
{
    use UuidModel;

    protected $table = 'tb_riwayat_kehamilan';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'status',
        'hari_pertama_haid_terakhir',
        'tanggal_perkiraan_lahir',
        'kehamilan_akhir',
    ];

    public function login()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }
}
