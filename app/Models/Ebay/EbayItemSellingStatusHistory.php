<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayItemSellingStatusHistory extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'ebay_item_selling_status_history'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'itemId',
        'currentPrice',
        'convertedCurrentPrice',
        'sellingState',
        'timeLeft',
    ];
}
