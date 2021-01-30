<?php

namespace App\Models;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;

class RequestSlab extends Model
{
    public $timestamps = true;

    protected $table = 'request_slab'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'player',
        'sport',
        'year',
        'brand',
        'card',
        'rc',
        'variation',
        'grade',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
