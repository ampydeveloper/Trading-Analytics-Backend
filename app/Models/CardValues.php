<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardValues extends Model
{
    use SoftDeletes;
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
