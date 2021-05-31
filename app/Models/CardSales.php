<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use DB;
use Carbon\Carbon;

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
        'ebay_items_id',
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

        $salesDate = CardSales::where('card_id', $id)->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $data['sx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
        } else {
            $data['sx'] = 0;
        }
        return $data;
    }

    public static function getSxGraph($days, $data) {
//        $data['sx'] = $data['values'][count($data['values']) - 1][1];

        if ($days == 2) {
            $data['sx'] = $data['values'][count($data['values']) - 1];
        } else {
            $data['sx'] = $data['values'][count($data['values']) - 1][1];
        }

        return $data;
    }

    public static function getSxAndLastSx($id) {
        $salesDate = CardSales::where('card_id', $id)->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $data['sx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
            $check_date1 = date('Y-m-d', strtotime($salesDate[1]));
            $data['lastSx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date1 . '%')->avg('cost');
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $data['sx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
            $data['lastSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['lastSx'] = 0;
        }
        return $data;
    }

    public static function getSlabstoxSx() {

        $salesDate = CardSales::groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $data['sx'] = CardSales::where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
            $check_date1 = date('Y-m-d', strtotime($salesDate[1]));
            $data['lastSx'] = CardSales::where('timestamp', 'like', '%' . $check_date1 . '%')->avg('cost');
            $check_date_oldest = date('Y-m-d', strtotime($salesDate[$count - 1]));
            $data['oldestSx'] = CardSales::where('timestamp', 'like', '%' . $check_date_oldest . '%')->avg('cost');
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $data['sx'] = CardSales::where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
            $data['lastSx'] = 0;
            $data['oldestSx'] = 0;
        } else {
            $data['sx'] = 0;
            $data['lastSx'] = 0;
            $data['oldestSx'] = 0;
        }
        return $data;
    }

    public static function getSlabstoxSxGraph($days, $data) {
        if ($days == 2 || $days == 7 || $days == 30 || $days == 90) {
            if ($days == 2) {
                $data['lastSx'] = $data['values'][0];
                $data['sx'] = $data['values'][count($data['values']) - 1];
            } else {
                $data['lastSx'] = $data['values'][0][1];
                $data['sx'] = $data['values'][count($data['values']) - 1][1];
            }
        } else {
            if ($data['start_date'] == $data['labels'][0]) {
                $data['lastSx'] = $data['values'][0][1];
            } else {
                $salesDate = CardSales::where('timestamp', '<', Carbon::createFromFormat('M/d/Y', $data['start_date'])->format('Y-m-d'))->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                if ($salesDate !== null) {
                    $data['lastSx'] = CardSales::where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                } else {
                    $data['lastSx'] = 0;
                }
            }
            $data['sx'] = $data['values'][count($data['values']) - 1][1];
        }
    }

    public static function getGraphSx($days, $data) {

        if ($days == 2 || $days == 7 || $days == 30 || $days == 90) {
            if ($days == 2) {
                $data['lastSx'] = $data['values'][0];
                $data['sx'] = $data['values'][count($data['values']) - 1];
            } else {
                $data['lastSx'] = $data['values'][0][1];
                $data['sx'] = $data['values'][count($data['values']) - 1][1];
            }
        } else {
             $data['lastSx'] = $data['values'][0][1];
//            if ($data['start_date'] == $data['labels'][0]) {
//                $data['lastSx'] = $data['values'][0][1];
//            } else {
//                $salesDate = CardSales::where('timestamp', '<', Carbon::createFromFormat('M/d/Y', $data['start_date'])->format('Y-m-d'))->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
//                if ($salesDate !== null) {
//                    $data['lastSx'] = CardSales::where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
//                } else {
//                    $data['lastSx'] = 0;
//                }
//            }
            $data['sx'] = $data['values'][count($data['values']) - 1][1];
        }
        return $data;
    }

    public static function getGraphSxWithCardId($days, $data, $card_id) {
        if (!empty($data['values'])) {
            if ($days == 2 || $days == 7 || $days == 30 || $days == 90) {
                if ($days == 2) {
                    $data['lastSx'] = $data['values'][0];
                    $data['sx'] = $data['values'][count($data['values']) - 1];
                } else {
                    $data['lastSx'] = $data['values'][0][1];
                    $data['sx'] = $data['values'][count($data['values']) - 1][1];
                }
            } else {
                $data['lastSx'] = $data['values'][0][1];
//            if ($data['start_date'] == $data['labels'][0]) {
//                $data['lastSx'] = $data['values'][0][1];
//            } else {
//                $salesDate = CardSales::where('card_id', $card_id)->where('timestamp', '<', Carbon::createFromFormat('M/d/Y', $data['start_date'])->format('Y-m-d H:i:s'))->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
//               
////                dd($salesDate);
//                if ($salesDate !== null) {
//                    $data['lastSx'] = CardSales::where('card_id', $card_id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
//                } else {
//                    $data['lastSx'] = 0;
//                }
//            }
                $data['sx'] = $data['values'][count($data['values']) - 1][1];
            }
            return $data;
        } else {
            $data['lastSx'] = 0;
            $data['sx'] = 0;
            return $data;
        }
    }

    public static function getGraphSxWithIds($days, $data, $card_ids) {

        if ($days == 2 || $days == 7 || $days == 30 || $days == 90) {
            if ($days == 2) {
                $data['lastSx'] = $data['values'][0];
                $data['sx'] = $data['values'][count($data['values']) - 1];
            } else {
                $data['lastSx'] = $data['values'][0][1];
                $data['sx'] = $data['values'][count($data['values']) - 1][1];
            }
        } else {
            if ($data['start_date'] == $data['labels'][0]) {
                $data['lastSx'] = $data['values'][0][1];
            } else {
                $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', Carbon::createFromFormat('M/d/Y', $data['start_date'])->format('Y-m-d'))->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                if ($salesDate !== null) {
                    $data['lastSx'] = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                } else {
                    $data['lastSx'] = 0;
                }
            }
            $data['sx'] = $data['values'][count($data['values']) - 1][1];
        }
        return $data;
    }

    public static function getSxAndOldestSx($id, $to, $from) {
//        dd($to);
//        if($id == 18153) {
//            dump($to);
//            dd(CardSales::where('card_id', $id)->whereBetween('timestamp', [$to, $from])->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp'));
//        }
//        dump('CARD ID:'. $id);
        $salesDate = CardSales::where('card_id', $id)->whereBetween('timestamp', [$to, $from])->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
//        echo $count;
//         dump($count);
//        if ($count >= 2) {
//            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
//            $data['sx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
//            $check_date_oldest = date('Y-m-d', strtotime($salesDate[$count - 1]));
//            $data['oldestSx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date_oldest . '%')->avg('cost');
//        } elseif ($count == 1) {
//            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
//            $data['sx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
//            $data['oldestSx'] = 0;
//        } else {
//            $data['sx'] = 0;
//            $data['oldestSx'] = 0;
//        }

        if ($count >= 2) {
//            dump($salesDate[0]);
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $data['sx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
//            dump($salesDate[$count - 1]);
//            dump($data['sx']);
            $check_date_oldest = date('Y-m-d', strtotime($salesDate[$count - 1]));
//            dump($to);
//            dump($check_date_oldest);
            $data['oldestSx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date_oldest . '%')->avg('cost');
//            $red = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date_oldest . '%')->get();
//            dump($red);
//            dump($data['oldestSx']);
            $start_date = date('Y-m-d', strtotime($to));
            if ($start_date != $check_date_oldest) {
                $salesDate = CardSales::where('card_id', $id)->where('timestamp', '<', $start_date)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
//               dd($salesDate);
                if ($salesDate !== null) {
                    $data['oldestSx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                } else {
                    $data['oldestSx'] = 0;
                }
            }
//             dump($data['oldestSx']);
        } else {
            if ($count == 1) {
                $check_date0 = date('Y-m-d H:i:s', strtotime($salesDate[0]));
                $data['sx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
                $salesDate = CardSales::where('card_id', $id)
                        ->where('timestamp', '<', $check_date0)
                        ->orderBy('timestamp', 'DESC')
                        ->first(DB::raw('DATE(timestamp)'));
                if ($salesDate !== null) {
                    $data['oldestSx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
//                    $red = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->get();
//               echo $id;
//               echo '<br>';
//                    dump($red);
//                    $data['oldestSx'] = 0;
                } else {
                    $data['oldestSx'] = 0;
                }
            } else {
                $salesDate = CardSales::where('card_id', $id)
                        ->where('timestamp', '<', $to)
                        ->orderBy('timestamp', 'DESC')
                        ->first(DB::raw('DATE(timestamp)'));
                if ($salesDate !== null) {
                    $data['oldestSx'] = CardSales::where('card_id', $id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                    $data['sx'] = $data['oldestSx'];
                } else {
                    $data['oldestSx'] = 0;
                    $data['sx'] = 0;
                }
            }
        }
        return $data;
    }

}
