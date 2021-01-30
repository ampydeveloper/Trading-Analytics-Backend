<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceSearchOptions extends Model
{
    protected $table = 'advance_search_options'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'keyword',
        'status',
    ];

    protected $cast = [
        'status' => 'boolen'
    ];
}
