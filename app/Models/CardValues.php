<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class CardValues extends Model
{
    use SoftDeletes;
    use LogsActivity;
    public $timestamps = true;

    protected $table = 'card_values'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'avg_value',
        'date',
    ];

    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

    protected $hidden = [
        'id',
        'deleted_at',
    ];

    protected $appends = [
        'value',
    ];

    public function getValueAttribute()
    {
        return number_format((float)$this->avg_value, 2, '.', '');
    }
}
