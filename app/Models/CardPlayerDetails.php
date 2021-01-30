<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardPlayerDetails extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'card_player_details'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'name',
        'sports',
        'team', 
    ];
}
