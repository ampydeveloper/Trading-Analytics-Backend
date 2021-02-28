<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardFollow extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'board_follows'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'board_id',
    ];

   
}
