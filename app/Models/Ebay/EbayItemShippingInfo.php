<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayItemShippingInfo extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'ebay_item_shipping_infos'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'itemId',
        'shippingServiceCost',
        'shippingType',
        'shipToLocations',
        'expeditedShipping',
        'oneDayShippingAvailable',
        'handlingTime',
    ];
}
