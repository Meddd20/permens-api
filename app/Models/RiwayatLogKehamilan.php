<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiwayatLogKehamilan extends Model
{
    use HasFactory;

    protected $table = 'tb_data_harian_kehamilan';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'data_harian' => 'json',
        'tekanan_darah' => 'json',
        'timer_kontraksi' => 'json',
        'gerakan_bayi' => 'json'
    ];
}
