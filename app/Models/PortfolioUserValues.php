<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortfolioUserValues extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'portfolio_user_value';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'value',
        'date',
    ];

    protected $hidden = [
        'id',
        'deleted_at',
    ];
}
