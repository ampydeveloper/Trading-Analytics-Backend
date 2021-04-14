<?php

namespace App\Models;

use App\Models\Auth\User;
use App\Models\Ebay\EbayItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class SeeProblem extends Model
{
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'see_problem'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'ebay_item_id',
        'message',
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ebay()
    {
        return $this->belongsTo(EbayItems::class, 'ebay_item_id');
    }
}
