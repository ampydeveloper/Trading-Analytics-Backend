<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;

class RequestListing extends Model
{
    public $timestamps = true;

    protected $table = 'request_listing'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'user_id',
        'link',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
