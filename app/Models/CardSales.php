<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use DB;

class CardSales extends Model {

    use SoftDeletes;

use LogsActivity;

    public $timestamps = true;
    protected $table = 'card_sales';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'timestamp',
        'excel_uploads_id',
        'quantity',
        'cost',
        'source',
        'type',
    ];
    protected static $logFillable = true;
    protected static $logOnlyDirty = true;

//    protected $hidden = [
//        'id',
//        'deleted_at',
//    ];
//
//    protected $appends = [
//        'value',
//    ];

    public static function getSx($id) {
//        $salesDate = CardSales::where('card_id', $id)->orderBy('timestamp', 'DESC')->distinct()->pluck('timestamp');
        $salesDate = CardSales::where('card_id', $id)->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
        } else {
            $data['sx'] = 0;
        }
        return $data;
    }

    public static function getSxAndLastSx($id) {
//        $salesDate = CardSales::where('card_id', $id)->orderBy('timestamp', 'DESC')->distinct()->pluck('timestamp');
        $salesDate = CardSales::where('card_id', $id)->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);

            $check_date1 = date('Y-m-d', strtotime($salesDate[1]));
            $lastSxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date1 . '%')->pluck('cost');
            $data['lastSx'] = array_sum($lastSxSale->toArray()) / count($lastSxSale);
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
            $data['lastSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['lastSx'] = 0;
        }
        return $data;
    }

    public static function getSlabstoxSx() {
//        $salesDate = CardSales::orderBy('timestamp', 'DESC')->distinct()->pluck('timestamp');
        $salesDate = CardSales::groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);

            $check_date1 = date('Y-m-d', strtotime($salesDate[1]));
            $lastSxSale = CardSales::where('timestamp', 'like', '%' . $check_date1 . '%')->pluck('cost');
            $data['lastSx'] = array_sum($lastSxSale->toArray()) / count($lastSxSale);
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
            $data['lastSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['lastSx'] = 0;
        }
        return $data;
    }
    
    public static function getGraphSx($from, $to) {
        $salesDate = CardSales::whereBetween('timestamp', [$to, $from])->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
//        dd($salesDate);
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);

            $check_date1 = date('Y-m-d', strtotime($salesDate[$count - 1]));
            $lastSxSale = CardSales::where('timestamp', 'like', '%' . $check_date1 . '%')->pluck('cost');
            $data['lastSx'] = array_sum($lastSxSale->toArray()) / count($lastSxSale);
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
            $data['lastSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['lastSx'] = 0;
        }
        return $data;
    }
    
    public static function getGraphSxWithCardId($id, $from, $to) {
        $salesDate = CardSales::where('card_id', $id)->whereBetween('timestamp', [$to, $from])->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
//        var_dump($salesDate);
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
//dd($sxSale);
            $check_date1 = date('Y-m-d', strtotime($salesDate[$count - 1]));
            $lastSxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date1 . '%')->pluck('cost');
            $data['lastSx'] = array_sum($lastSxSale->toArray()) / count($lastSxSale);
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
            $data['lastSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['lastSx'] = 0;
        }
        return $data;
    }
    public static function getGraphSxWithIds($ids, $from, $to) {
        $salesDate = CardSales::whereIn('card_id', $ids)->whereBetween('timestamp', [$to, $from])->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::whereIn('card_id', $ids)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);

            $check_date1 = date('Y-m-d', strtotime($salesDate[$count - 1]));
            $lastSxSale = CardSales::whereIn('card_id', $ids)->where('timestamp', 'like', '%' . $check_date1 . '%')->pluck('cost');
            $data['lastSx'] = array_sum($lastSxSale->toArray()) / count($lastSxSale);
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::whereIn('card_id', $ids)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
            $data['lastSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['lastSx'] = 0;
        }
        return $data;
    }

    public static function getSxAndOldestSx($id) {
//        $salesDate = CardSales::where('card_id', $id)->orderBy('timestamp', 'DESC')->distinct()->pluck('timestamp');
        $salesDate = CardSales::where('card_id', $id)->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);

            $check_date_oldest = date('Y-m-d', strtotime($salesDate[$count - 1]));
            $oldestSxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date_oldest . '%')->pluck('cost');
            $data['oldestSx'] = array_sum($oldestSxSale->toArray()) / count($oldestSxSale);
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sxSale = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->pluck('cost');
            $data['sx'] = array_sum($sxSale->toArray()) / count($sxSale);
            $data['oldestSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['oldestSx'] = 0;
        }
        return $data;
    }

}
