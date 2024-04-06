<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;

class RiwayatLog extends Model
{
    use UuidModel;

    protected $table = 'tb_data_harian';
    protected $keyType = 'string';
    public $incrementing = false;

    // Enable timestamps
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'data_harian',
        'pengingat'
    ];

    protected $casts = [
        'data_harian' => 'json',
        'pengingat' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }
}
