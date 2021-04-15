<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSettings extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slab_image',
        'listing_image',
        'trenders_order',
        'live_listings_order'
    ];

    protected $hidden = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function getSlabImageAttribute($value){
        if(!strpos($value, "storage") !== false){
            $value = "storage/" . $value;
        }
        return $value != null ? url($value) : '';
    }

    public function getListingImageAttribute($value){
        if (!strpos($value, "storage") !== false) {
            $value = "storage/" . $value;
        }
        return $value != null ? url($value) : '';
    }

    public function getTrendersOrderAttribute($value){
        return $value != null ? json_decode($value) : ['basketball', 'soccer', 'baseball', 'football', 'pokemon'];
    }

    public function getLiveListingsOrderAttribute($value){
        return $value != null ? json_decode($value) : ['basketball', 'soccer', 'baseball', 'football', 'pokemon'];
    }
}
