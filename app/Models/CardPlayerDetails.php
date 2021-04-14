<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CardPlayerDetails extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'card_player_details'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'name',
        'sports',
        'team', 
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
}
