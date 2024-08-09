<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artikel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tb_artikel';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function artikel(): HasMany {
        return $this->hasMany(Artikel::class, 'article_id');
    }

    public function login(): BelongsTo {
        return $this->belongsTo(Login::class, 'user_id');
    }
}
