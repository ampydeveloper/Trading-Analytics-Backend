<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use SoftDeletes;
    public $timestamps = true;

    protected $table = 'cards'; 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'row_id',
        'excel_uploads_id',
        'sport',
        'player',
        'year',
        'brand',
        'card',
        'rc',
        'variation',
        'grade',
        'qualifiers',
        'qualifiers2',
        'qualifiers3',
        'qualifiers4',
        'qualifiers5',
        'qualifiers6',
        'qualifiers7',
        'qualifiers8',
        'readyforcron',
        'is_featured',
        'active',
        'image',
        'title',
        'views'
    ];

    protected $appends = [
        'cardImage'
    ];

    public function getCardImageAttribute()
    {
         if($this->image != null){
            return url("storage/" . $this->image);
//            return url("storage/".strtolower($this->sport). '/' . $this->image);
        }
        else{
            return asset('/img/default-image.jpg');
        }
    }

    public function getImageFileName($id, $sport)
    {
        $card_url = public_path('storage/'.$sport.'/F' . $id . '.jpg');
        if(file_exists($card_url)){
            return env('APP_URL').'/storage/'.$sport.'/F'.$id.'.jpg';
        }else{
            if($id > 0) {
                return $this->getImageFileName(($id-1), $sport);
            }else{
                return false;
            }
        }
    }

    public function value()
    {
        return $this->hasOne(CardValues::class, 'card_id');
    }

    public function details()
    {
        return $this->hasOne(CardDetails::class, 'card_id');
    }
    public function player_details()
    {
        return $this->hasOne(CardPlayerDetails::class, 'card_id');
    }
    
     public function sales()
    {
        return $this->hasMany(CardSales::class, 'card_id');
    }
}
