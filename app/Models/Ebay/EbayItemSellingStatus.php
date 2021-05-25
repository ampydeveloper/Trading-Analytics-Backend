<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class EbayItemSellingStatus extends Model
{
    use SoftDeletes;
    use LogsActivity;
    

    public $timestamps = true;

    protected $table = 'ebay_item_selling_statuses'; 
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

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    protected $appends = [
        'price',
    ];

    public function getPriceAttribute()
    {
//        return number_format((float)$this->currentPrice, 2, '.', '');
        return $this->currentPrice;
    }

}
