<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Komentar extends Model
{
    use HasFactory;
    use UuidModel;
    use SoftDeletes;

    protected $table = 'tb_komentar';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'article_id',
        'parent_id',
        'parent_comment_user_id',
        'content',
        'upvotes',
        'downvotes',
        'is_pinned',
        'is_hidden',
        'is_flagged',
        'flagged_notes',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function login()
    {
        return $this->belongsTo(Login::class, 'user_id', 'id');
    }

    public function article()
    {
        return $this->belongsTo(Artikel::class, 'article_id', 'id');
    }
}
