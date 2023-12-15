<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Model;

class RiwayatLog extends Model
{
    use UuidModel;

    protected $table = 'tb_data_harian';

    // Enable timestamps
    public $timestamps = true;

    protected $fillable = [
        'id',
        'user_id',
        'data_harian',
    ];

    protected $casts = [
        'data_harian' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }
}
