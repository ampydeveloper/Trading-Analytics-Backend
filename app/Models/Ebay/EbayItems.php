<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

use App\Models\Ebay\EbayItemCategories;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardPlayerDetails;

class EbayItems extends Model
{
    use SoftDeletes;
    use LogsActivity;

    public $timestamps = true;

    protected $table = 'ebay_items'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'itemId',
        'card_id',
        'excel_uploads_id',
        'title',
        'globalId',
        'category_id',
        'galleryURL',
        'viewItemURL',
        'pictureURLSuperSize',
        'pictureURLLarge',
        'paymentMethod',
        'autoPay',
        'postalCode',
        'location',
        'country',
        'shipping_info_id',
        'seller_info_id',
        'selling_status_id',
        'listing_info_id',
        'returnsAccepted',
        'condition_id',
        'isMultiVariationListing',
        'topRatedListing',
        'listing_ending_at',
        'is_random_bin',
        'sold_price',
        'status'
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;


    public function category()
    {
        return $this->belongsTo(EbayItemCategories::class,'category_id');
    }

    public function card()
    {
        return $this->belongsTo(Card::class,'card_id');
    }

    public function condition()
    {
        return $this->belongsTo(EbayItemCondition::class,'condition_id');
    }

    public function listingInfo()
    {
        return $this->belongsTo(EbayItemListingInfo::class,'listing_info_id');
    }

    public function sellerInfo()
    {
        return $this->belongsTo(EbayItemSellerInfo::class,'seller_info_id');
    }

    public function sellingStatus()
    {
        return $this->belongsTo(EbayItemSellingStatus::class,'selling_status_id');
    }

    public function shippingInfo()
    {
        return $this->belongsTo(EbayItemShippingInfo::class,'shipping_info_id');
    }

    public function specifications()
    {
        return $this->hasMany(EbayItemSpecific::class,'itemId','itemId');
    }

    public function details()
    {
        return $this->belongsTo(CardDetails::class,'card_id','card_id');
    }

    public function playerDetails()
    {
        return $this->belongsTo(CardPlayerDetails::class,'card_id','card_id');
    }
}
