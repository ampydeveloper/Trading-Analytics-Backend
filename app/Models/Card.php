<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\AppSettings;
use Illuminate\Support\Facades\Storage;
use App\Models\Ebay\EbayItems;

class Card extends Model {

    use SoftDeletes;

use LogsActivity;

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
        'is_sx',
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
    protected static $logFillable = true;
    protected static $logOnlyDirty = true;
    protected $appends = [
        'cardImage'
    ];

    public function getCardImageAttribute() {
        if ($this->image != null && $this->image != "0" && Storage::disk('s3')->has($this->image)) {
            return Storage::disk('s3')->url($this->image);
        } else {
            $settings = AppSettings::first();
            if (!empty($settings) && $this->sport != null) {
                $default_filename = strtolower($this->sport) . '_image';
                return $settings->sports_images[$default_filename];
            } else {
                return asset('/img/default-image.jpg');
            }
        }
    }

    public function getImageFileName($id, $sport) {
        $card_url = public_path('storage/' . $sport . '/F' . $id . '.jpg');
        if (file_exists($card_url)) {
            return env('APP_URL') . '/storage/' . $sport . '/F' . $id . '.jpg';
        } else {
            if ($id > 0) {
                return $this->getImageFileName(($id - 1), $sport);
            } else {
                return false;
            }
        }
    }

    public function value() {
        return $this->hasOne(CardValues::class, 'card_id');
    }

    public function details() {
        return $this->hasOne(CardDetails::class, 'card_id');
    }

    public function player_details() {
        return $this->hasOne(CardPlayerDetails::class, 'card_id');
    }

    public function sales() {
        return $this->hasMany(CardSales::class, 'card_id');
    }
    
    public function cardSx() {
        return $this->hasOne(CardsSx::class, 'card_id');
    }
    public function ebayItems() {
        return $this->hasMany(EbayItems::class, 'card_id');
    }

}
