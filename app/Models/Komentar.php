<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Komentar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tb_komentar';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function komentarLike(): HasMany {
        return $this->hasMany(KomentarLike::class, 'comment_id');
    }

    public function article(): BelongsTo {
        return $this->belongsTo(Artikel::class, 'article_id');
    }

    public function login(): BelongsTo {
        return $this->belongsTo(Login::class, 'user_id');
    }
}
