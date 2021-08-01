<?php

namespace App\Models;

use App\Models\Auth\User;
use App\Models\Card;
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
        'new_card_id'
    ];
    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    public function user()
    {
//        return $this->belongsTo(User::class, 'user_id');
        return $this->belongsTo(User::class, 'user_id')->select(['id']);
    }

    public function card()
    {
//        return $this->belongsTo(Card::class, 'card_id');
        return $this->belongsTo(Card::class, 'card_id')->select(['title', 'id']);
    }
}
