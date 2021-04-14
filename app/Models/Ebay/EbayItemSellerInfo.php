<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class EbayItemSellerInfo extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'ebay_item_seller_infos'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'itemId',
        'sellerUserName',
        'feedbackScore',
        'positiveFeedbackPercent',
        'feedbackRatingStar',
        'topRatedSeller',
        'seller_contact_link',
        'seller_store_link'
    ];
    
    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
}
