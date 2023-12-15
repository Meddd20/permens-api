<?php

namespace App\Models;

use App\Traits\UuidModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artikel extends Model
{
    use UuidModel;
    use SoftDeletes;

    protected $table = 'tb_artikel';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        "writter",
        "title_ind",
        "title_eng",
        "slug_title_ind",
        "slug_title_eng",
        "banner",
        "content_ind",
        "content_eng",
        "video_link",
        "source",
        "tags"
    ];
}
