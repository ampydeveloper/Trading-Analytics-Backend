<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayItemSellerInfo extends Model
{
    use SoftDeletes;
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
    ];
}
