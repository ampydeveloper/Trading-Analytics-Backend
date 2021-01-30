<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EbayItemSpecific extends Model
{
    use SoftDeletes;
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
}
