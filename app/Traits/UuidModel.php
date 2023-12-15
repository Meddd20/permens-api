<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UuidModel
{
    protected static function bootUuidModel()
    {
        static::creating(function($model){
             if(empty($model->{$model->getKeyName()})){
                 $model->{$model->getKeyName()} = Str::uuid(); 
             }
        });
    }
}
