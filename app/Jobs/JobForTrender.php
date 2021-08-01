<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use DateTime;
use Illuminate\Support\Facades\Cache;
use App\Models\AppSettings;
use App\Models\CardsSx;
use App\Models\CardsTotalSx;
use Illuminate\Support\Facades\DB;

class JobForTrender implements ShouldQueue {

    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct() {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        $days = [
            1 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'daysForSx' => 0
            ],
//            2 => [
//                'from' => date('Y-m-d H:i:s'),
//                'to' => date('Y-m-d H:i:s', strtotime('-8 day')),
//                'daysForSx' => 7
//            ],
//            3 => [
//                'from' => date('Y-m-d H:i:s'),
//                'to' => date('Y-m-d H:i:s', strtotime('-30 day')),
//                'daysForSx' => 30
//            ],
//            4 => [
//                'from' => date('Y-m-d H:i:s'),
//                'to' => date('Y-m-d H:i:s', strtotime('-90 day')),
//                'daysForSx' => 90
//            ],
//            5 => [
//                'from' => date('Y-m-d H:i:s'),
//                'to' => date('Y-m-d H:i:s', strtotime('-180 day')),
//                'daysForSx' => 180
//            ],
//            6 => [
//                'from' => date('Y-m-d H:i:s'),
//                'to' => date('Y-m-d H:i:s', strtotime('-365 day')),
//                'daysForSx' => 365
//            ],
//            7 => [
//                'from' => date('Y-m-d H:i:s'),
//                'to' => date('Y-m-d H:i:s', strtotime('-1825 day')),
//                'daysForSx' => 1825
//            ],
        ];
        $sports = [
            0 => 'basketball',
            1 => 'soccer',
            2 => 'baseball',
            3 => 'football',
            4 => 'hockey',
            5 => 'pokemon',
        ];

        foreach ($days as $daykey => $day) {
            foreach ($sports as $sport) {
                $name = 'trenders_' . $daykey . '_' . $sport;
                Cache::forget($name);
                $trender = Cache::rememberForever($name, function() use($day, $sport) {
                            $cards = [];
                            $card_sales = CardSales::whereBetween('timestamp', [$day['to'], $day['from']])->groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
                            if (!empty($card_sales)) {
                                $cards = Card::whereHas('sales', function($q) use($day, $sport) {
                                            $q->whereBetween('timestamp', [$day['to'], $day['from']]);
                                        }, '>=', 2)->where('sport', $sport)->where('active', 1)->with('details')->orderByRaw('FIELD (id, ' . implode(', ', $card_sales) . ') ASC')->get();

                                $cards = $cards->map(function ($card, $key) use($day) {
                                    $data = $card;
                                    $sx_data = CardSales::getSxAndOldestSx($card->id, $day['to'], $day['from'], $day['daysForSx']);
                                    $sx = $sx_data['sx'];
                                    $lastSx = $sx_data['oldestSx'];
                                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                                    $data['price'] = number_format((float) $sx, 2, '.', '');
                                    $data['sx_value_signed'] = (float) $sx - $lastSx;
                                    $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                    $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                    $data['sx_percent_signed'] = $sx_percent;
                                    $data['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                    $data['sx_icon'] = $sx_icon;
                                    $data['sale_qty'] = CardSales::where('card_id', $card->id)->sum('quantity');
                                    return $data;
                                });
                            }
                            return $cards;
                        });
            }
        }
        Cache::forget('trenders_all_cards');
        $trender = Cache::rememberForever('trenders_all_cards', function() {
                    $cards = [];
                    $card_sales = CardSales::groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
                    if (!empty($card_sales)) {
                        $cards = Card::whereHas('sales', function($q) {
                                    
                                }, '>=', 2)->where('active', 1)->with('details')->orderByRaw('FIELD (id, ' . implode(', ', $card_sales) . ') ASC')->get();

                        $cards = $cards->map(function ($card, $key) {
                            $data = $card;
                            $sx_data = CardSales::getSxAndOldestSx($card->id);
                            $sx = $sx_data['sx'];
                            $lastSx = $sx_data['oldestSx'];
                            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                            $data['price'] = number_format((float) $sx, 2, '.', '');
                            $data['sx_value_signed'] = (float) $sx - $lastSx;
                            $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                            $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                            $data['sx_percent_signed'] = $sx_percent;
                            $data['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                            $data['sx_icon'] = $sx_icon;
                            $data['sale_qty'] = CardSales::where('card_id', $card->id)->sum('quantity');
                            return $data;
                        });
                    }
                    return $cards;
                });
    }

}
