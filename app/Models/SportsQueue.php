<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportsQueue extends Model
{
    // use SoftDeletes;
    public $timestamps = true;

    protected $table = 'sports_queue'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'sport',
    ];
   
}
