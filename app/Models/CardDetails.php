<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CardDetails extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'card_details'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'number',
        'product',
        'season',
        'rookie',
        'series',
        'grade',
        'manufacturer',
        'era',
        'year',
        'grader',
        'autographed',
        'brand',
        'currentPrice',
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    protected $hidden = [
        'id',
        'card_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
