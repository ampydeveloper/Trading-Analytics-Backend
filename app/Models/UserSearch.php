<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Auth\User;
//use App\Models\card;

class UserSearch extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'user_search'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'user_id',
        'search',
    ];
    
    public function userDetails()
    {
            return $this->belongsTo(User::class, 'user_id');
    }
    public function cardDetails()
    {
            return $this->belongsTo(Card::class, 'card_id');
    }
}
