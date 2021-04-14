<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class EbayItemShippingInfo extends Model
{
    use SoftDeletes;
    use LogsActivity;
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

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
}
