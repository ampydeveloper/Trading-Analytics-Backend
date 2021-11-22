<?php

namespace App\Models;

use App\Models\Auth\User;
use App\Models\Card;
use App\Models\Ebay\EbayShortItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class RequestListing extends Model
{
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'request_listing'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'user_id',
        'link',
        'approved',
        'new_card_id',
        'comparison'
    ];
    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    public function user()
    {
//        return $this->belongsTo(User::class, 'user_id');
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'first_name']);
    }

    public function card()
    {
//        return $this->belongsTo(Card::class, 'card_id');
        return $this->belongsTo(Card::class, 'card_id')->select(['title', 'id', 'image']);
    }
    
    public function cardForMatch()
    {
//        return $this->belongsTo(Card::class, 'card_id');
        return $this->belongsTo(Card::class, 'card_id')->select(['title', 'id',"player","year","brand","variation","grade","image"]);
    }
    
    public function ebayShortItem() {
        return $this->hasOne(EbayShortItem::class,'request_listing_id');
    }
}
