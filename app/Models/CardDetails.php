<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardDetails extends Model
{
    use SoftDeletes;
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

    protected $hidden = [
        'id',
        'card_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
