<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class EbayItemCondition extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'ebay_item_conditions'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'itemId',
        'conditionId',
        'conditionDisplayName',
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
}
