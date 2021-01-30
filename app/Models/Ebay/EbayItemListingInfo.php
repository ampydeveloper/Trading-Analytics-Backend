<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayItemListingInfo extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'ebay_item_listing_infos'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'itemId',
        'bestOfferEnabled',
        'buyItNowAvailable',
        'startTime',
        'endTime',
        'listingType',
        'gift',
        'watchCount',
    ];
}
