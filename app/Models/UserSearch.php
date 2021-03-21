<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];
    
    public function userDetails()
    {
        return $this->hasOne(Auth\User::class, 'id');
    }
    public function cardDetails()
    {
        return $this->hasOne(Card::class, 'id');
    }
}
