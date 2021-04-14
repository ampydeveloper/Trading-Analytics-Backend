<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class EbayItemListingInfo extends Model
{
    use SoftDeletes;
    use LogsActivity;

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

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
}
