<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardSales extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'card_sales'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'timestamp',
        'quantity',
        'cost',
        'source'
    ];

//    protected $hidden = [
//        'id',
//        'deleted_at',
//    ];
//
//    protected $appends = [
//        'value',
//    ];

}
