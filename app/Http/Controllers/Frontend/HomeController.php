<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
// use App\Models\MyPortfolio;
use Illuminate\Http\Request;
use App\Services\EbayService;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardSales;
//use App\Jobs\ProcessCardsForRetrivingDataFromEbay;
//use App\Jobs\ProcessCardsComplieData;
//use App\Jobs\CompareEbayImagesWithCardImages;
use App\Models\Ebay\EbayItems;
use Carbon\Carbon;
use App\Models\Board;
use DB;
use App\Models\AppSettings;
//use App\Models\CardsSx;
use App\Models\CardsTotalSx;

/**
 * Class HomeController.
 */
class HomeController extends Controller {

    /**
     * @return \Illuminate\View\View
     */
    public function index() {
        return view('frontend.index');
    }

    public function cardData(Request $request) {
        $card_id = $request->input('id');
        $card_details = Card::where('id', $card_id)->with('details')->firstOrFail()->toArray();

//        $sx = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//        $sx_count = count($sx);
//        $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;

        $sx_data = CardSales::getSxAndLastSx($card_id);
        $sx = $sx_data['sx'];
        $lastSx = $sx_data['lastSx'];

        if (!empty($sx)) {
            $card_details['sx'] = number_format($sx, 2, '.', '');
//            $lastSx = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//            $lastSx = count($lastSx);
            $card_details['dollar_diff'] = number_format(abs($sx - $lastSx), 2, '.', '');
            $perc_diff = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
            $card_details['pert_diff'] = str_replace('-', '', number_format($perc_diff, 2, '.', ''));
        } else {
            $card_details['sx'] = 0;
            $card_details['dollar_diff'] = 0;
            $card_details['pert_diff'] = 0;
        }

        return view('frontend.card-data', compact('card_details'));
    }

    public function stoxtickerData(Request $request) {
        $data = ['total' => 0, 'sale' => 0, 'avg_sale' => 0, 'change' => 0, 'change_arrow' => 'up', 'last_updated' => ''];
        $data['total'] = Card::where('active', 1)->count();

$total = AppSettings::first();
            $data['sale'] = $total['total_sx_value'];


        $data['last_updated'] = 'N/A';
        $last_updated = CardSales::orderBy('timestamp', 'DESC')->first();
        if (!empty($last_updated)) {
            $data['last_updated'] = Carbon::create($last_updated->timestamp)->format('F d Y \- h:i:s A');
        }

         $salesDate = CardsTotalSx::groupBy(DB::raw('DATE(date)'))->orderBy('date', 'DESC')->take(2)->get();
            $count = $salesDate->count();
            if ($count >= 2) {
                $sx_data['sx'] = $salesDate[0]->amount;
                $sx_data['lastSx'] = $salesDate[1]->amount;
            } elseif ($count == 1) {
                $sx_data['sx'] = $salesDate[0]->amount;
                $sx_data['lastSx'] = 0;
            } else {
                $sx_data['sx'] = 0;
                $sx_data['lastSx'] = 0;
            }

            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $diff = abs($sx - $lastSx);
            $percent_diff = ($lastSx > 0 ? (($diff / $lastSx) * 100) : 0 );
            $data['change'] = number_format(abs($percent_diff), 2, '.', '');
            $data['change_pert'] = str_replace('-', '', number_format($percent_diff, 2, '.', ''));
            
//        $sx_data = CardSales::getSlabstoxSx();
//        $sx = $sx_data['sx'];
//        $lastSx = $sx_data['lastSx'];
//        $data['change'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
//        $perc_diff = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
//        $data['change_pert'] = str_replace('-', '', number_format($perc_diff, 2, '.', ''));
//        dd($data);
        return view('frontend.stoxticker-data', compact('data'));
    }

    public function stoxtickerDetailsData(Request $request) {
        $board_id = $request->input('board');
        $finalData['board'] = $board = Board::where('id', $board_id)->first();
        $all_cards = json_decode($board->cards);
//        $total_card_value = 0;
//        foreach ($all_cards as $key => $card) {
//            $sale = CardSales::getSx($card);
//            $total_card_value = $total_card_value + $sale['sx'];
//        }
           $total_card_value = CardSales::whereIn('card_id', $all_cards)->sum('cost');

        $salesDate = CardSales::whereIn('card_id', $all_cards)->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        $count = $salesDate->count();
        if ($count >= 2) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sx = CardSales::whereIn('card_id', $all_cards)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');

            $check_date1 = date('Y-m-d', strtotime($salesDate[$count - 1]));
            $lastSx = CardSales::whereIn('card_id', $all_cards)->where('timestamp', 'like', '%' . $check_date1 . '%')->avg('cost');
        } elseif ($count == 1) {
            $check_date0 = date('Y-m-d', strtotime($salesDate[0]));
            $sx = CardSales::whereIn('card_id', $all_cards)->where('timestamp', 'like', '%' . $check_date0 . '%')->avg('cost');
            $lastSx = 0;
        } else {
            $sx = 0;
            $lastSx = 0;
        }

        if (!empty($sx)) {
//            $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(3)->limit(3)->pluck('cost');
//            $count = count($lastSx);
//            $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
            if ($sx != 0) {
                $perc_diff = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                $finalData['pert_diff'] = number_format((float) $perc_diff, 2, '.', '');
            }
            $finalData['sx_value'] = number_format((float) $sx, 2, '.', '');
            $finalData['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');
        } else {
            $finalData['pert_diff'] = 0;
            $finalData['sx_value'] = 0;
            $finalData['total_card_value'] = 0;
        }
        return view('frontend.stoxticker-details-data', compact('finalData'));
    }

    public function getSoldListings(Request $request) {
        $items['basketball'] = EbayItems::whereHas('card', function($q) use($request) {
                    $q->where('sport', 'basketball');
                })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(1)->get();
        $items['football'] = EbayItems::whereHas('card', function($q) use($request) {
                    $q->where('sport', 'football');
                })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(1)->get();
        $items['baseball'] = EbayItems::whereHas('card', function($q) use($request) {
                    $q->where('sport', 'baseball');
                })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(1)->get();
        $items['soccer'] = EbayItems::whereHas('card', function($q) use($request) {
                    $q->where('sport', 'soccer');
                })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(1)->get();
        $items['pokemon'] = EbayItems::whereHas('card', function($q) use($request) {
                    $q->where('sport', 'pokemon');
                })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(1)->get();
//            $board = Board::where('id', $board)->first();
//            $all_cards = json_decode($board->cards);
//            foreach ($all_cards as $card) {
//                $each_cards[] = Card::where('id', (int) $card)->with('details')->first();
//            }
//            $finalData = $this->__cardData();
//            return response()->json(['status' => 200, 'data' => $items], 200);
        return view('frontend.stoxticker-sx-data', compact('items'));
    }

}
