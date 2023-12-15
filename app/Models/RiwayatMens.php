<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;

class RiwayatMens extends Model
{
    use UuidModel;

    protected $table = 'tb_riwayat_mens';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'usia',
        'usia_lunar',
        'haid_awal',
        'haid_akhir',
        'ovulasi',
        'masa_subur_awal',
        'masa_subur_akhir',
        'mid_period_awal',
        'mid_period_akhir',
        'tiga_hari_sebelum_masa_subur',
        'lama_siklus',
        'durasi_haid',
        'haid_berikutnya_awal',
        'haid_berikutnya_akhir',
        'ovulasi_berikutnya',
        'masa_subur_berikutnya_awal',
        'masa_subur_berikutnya_akhir',
        'mid_period_berikutnya_awal',
        'mid_period_berikutnya_akhir',
        'tiga_hari_sebelum_masa_ovulasi_berikutnya',
        'is_actual'
    ];

    public function login()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }
}
