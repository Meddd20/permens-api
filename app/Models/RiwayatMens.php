<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatMens extends Model
{
    use HasFactory;
    
    protected $table = 'tb_riwayat_mens';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function login(): BelongsTo {
        return $this->belongsTo(Login::class, 'user_id');
    }
}
