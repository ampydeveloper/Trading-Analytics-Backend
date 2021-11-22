<?php

namespace App\Models\Ebay;

use App\Models\Auth\User;
use App\Models\RequestListing;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class EbayShortItem extends Model
{
    // use LogsActivity;
    // public $timestamps = true;

    protected $table = 'ebay_short_items'; 
    protected $fillable = [
        'request_listing_id',
        'title',
        'ebay_id',
        'price',
        'image',
        'time_left',
        "specifics"
    ];

    public function getSpecificsAttribute($value) 
    {
        return (array)json_decode(str_replace(':"','"',$value));
    }
    
    public function getTimeLeftAttribute($value) {
        date_default_timezone_set("America/Los_Angeles");
        $datetime1 = new \DateTime($value);
        $datetime2 = new \DateTime('now');
        $interval = $datetime1->diff($datetime2);
        if ($interval->invert == 1) {
            $days = $interval->format('%d');
            $hours = $interval->format('%h');
            $mins = $interval->format('%i');
            $secs = $interval->format('%s');
            if ($days > 0) {
                $timeleft = $days . 'd ' . $hours . 'h';
            } else if ($hours >= 1) {
                $timeleft = $hours . 'h ' . $mins . 'm';
            } else if ($mins >= 1) {
                $timeleft = $mins . 'm ' . $secs . 's';
            } else {
                $timeleft = $secs . 's';
            }
        } else {
            $timeleft = '0s';
        }
        
        return $timeleft;
    }
    // protected static $logFillable = true;
    // protected static $logOnlyDirty = true;

//     public function user()
//     {
// //        return $this->belongsTo(User::class, 'user_id');
//         return $this->belongsTo(User::class, 'user_id')->select(['id', 'first_name']);
//     }

    public function requestListing()
    {
       return $this->belongsTo(RequestListing::class,'id');
        // return $this->belongsTo(Card::class, 'card_id')->select(['title', 'id']);
    }
}
