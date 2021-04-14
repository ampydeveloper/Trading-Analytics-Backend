<?php

namespace App\Models\Ebay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

use App\Models\Ebay\EbayItems;

class EbayItemCategories extends Model
{
    use LogsActivity;
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

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    public function items()
    {
        return $this->hasMany(EbayItems::class,'id','category_id');
    }
}
