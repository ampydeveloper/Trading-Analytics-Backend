<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Ebay\EbayItems;

class EbayItemCategories extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'ebay_item_categories'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'categoryId',
        'name',
    ];

    public function items()
    {
        return $this->hasMany(EbayItems::class,'id','category_id');
    }
}
