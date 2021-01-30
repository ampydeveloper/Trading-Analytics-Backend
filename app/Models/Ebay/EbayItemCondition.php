<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayItemCondition extends Model
{
    use SoftDeletes;
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
}
