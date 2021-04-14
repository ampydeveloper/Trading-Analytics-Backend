<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class EbayItemSpecific extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'ebay_item_specifics'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'itemId',
        'name',
        'value',
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
}
