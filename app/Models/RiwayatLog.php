<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatLog extends Model
{
    use HasFactory;

    protected $table = 'tb_data_harian';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'data_harian' => 'json',
        'pengingat' => 'json',
    ];

    public function login(): BelongsTo {
        return $this->belongsTo(Login::class, 'user_id');
    }
}
