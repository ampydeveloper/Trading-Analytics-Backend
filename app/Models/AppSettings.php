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
        'basketball_image',
        'baseball_image',
        'football_image',
        'soccer_image',
        'pokemon_image',
        'listing_image',
        'trenders_order',
        'live_listings_order'
    ];

    protected $hidden = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function getBasketballImageAttribute($value){
        if(!strpos($value, "storage") !== false){
            $value = "storage/" . $value;
        }
        return $value != null ? url($value) : '';
    }
    public function getBaseballImageAttribute($value){
        if(!strpos($value, "storage") !== false){
            $value = "storage/" . $value;
        }
        return $value != null ? url($value) : '';
    }
    public function getFootballImageAttribute($value){
        if(!strpos($value, "storage") !== false){
            $value = "storage/" . $value;
        }
        return $value != null ? url($value) : '';
    }
    public function getSoccerImageAttribute($value){
        if(!strpos($value, "storage") !== false){
            $value = "storage/" . $value;
        }
        return $value != null ? url($value) : '';
    }
    public function getPokemonImageAttribute($value){
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
