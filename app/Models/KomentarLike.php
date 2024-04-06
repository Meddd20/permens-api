<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KomentarLike extends Model
{
    use HasFactory;
    use UuidModel;

    protected $table = 'tb_like_komentar';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'article_id',
        'comment_id',
    ];

    public function login()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }

    public function article()
    {
        return $this->belongsTo(Artikel::class, 'article_id', 'id');
    }

    public function comment()
    {
        return $this->belongsTo(Komentar::class, 'comment_id', 'id');
    }
}
