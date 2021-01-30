<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MyPortfolio extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'my_portfolio'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'card_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class,'card_id');
    }
}
