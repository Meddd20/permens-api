<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KomentarLike extends Model
{
    use HasFactory;

    protected $table = 'tb_like_komentar';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function login(): BelongsTo {
        return $this->belongsTo(Login::class, 'user_id');
    }

    public function comment(): BelongsTo {
        return $this->belongsTo(Komentar::class, 'comment_id');
    }
}
