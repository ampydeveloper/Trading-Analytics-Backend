<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CardsTotalSx extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'cards_total_sx'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'quantity',
        'amount',
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

}
