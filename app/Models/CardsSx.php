<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CardsSx extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'cards_sx'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'date',
        'sx',
        'quantity',
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    protected $hidden = [
//        'id',
//        'card_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    public function card()
    {
        return $this->belongsTo(Card::class,'card_id');
    }
}
