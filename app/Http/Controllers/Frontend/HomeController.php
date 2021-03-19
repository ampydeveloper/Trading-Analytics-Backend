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
//use App\Models\Ebay\EbayItems;
use Carbon\Carbon;
use App\Models\Board;

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

        $sx = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
        $card_details['sx'] = number_format((float) $sx, 2, '.', '');
        $lastSx = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
        $lastSx = count($lastSx);
        $card_details['dollar_diff'] = number_format($sx - $lastSx, 2, '.', '');
        $card_details['pert_diff'] = number_format($lastSx / $sx * 100, 2, '.', '');

        return view('frontend.card-data', compact('card_details'));
    }

    public function stoxtickerData(Request $request) {
        $data = ['total' => 0, 'sale' => 0, 'avg_sale' => 0, 'change' => 0, 'change_arrow' => 'up', 'last_updated' => ''];
        $data['total'] = Card::count();
        $cs_cost = CardSales::sum('cost');
        $data['sale'] = number_format((float) $cs_cost, 2, '.', '');
        $last_updated = CardSales::orderBy('timestamp', 'DESC')->first();
        if (!empty($last_updated)) {
            $data['last_updated'] = Carbon::create($last_updated->timestamp)->format('F d Y \- h:i:s A');
        }

        $data['doller_diff'] = 0;
        $sales_diff = CardSales::orderBy('timestamp', 'DESC')->take(2)->get();
        if (isset($sales_diff[1])) {
            $doller_diff = $sales_diff[1]->cost - $sales_diff[0]->cost;
            $data['doller_diff'] = str_replace('-', '', $doller_diff);
        }

        return view('frontend.stoxticker-data', compact('data'));
    }

    public function stoxtickerDetailsData(Request $request) {
        $board_id = $request->input('board');
        $finalData['board'] = $board = Board::where('id', $board_id)->first();
        $all_cards = json_decode($board->cards);
        $total_card_value = 0;
        foreach ($all_cards as $key => $card) {
//                $each_cards[$key]['card_data'] = Card::where('id', (int) $card)->with('details')->first();
            $sx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
            $total_card_value = $total_card_value + $sx;
//                $lastSx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
//                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
//                $each_cards[$key]['card_data']['sx_value'] = number_format((float) $sx, 2, '.', '');
//                $each_cards[$key]['card_data']['sx_icon'] = $sx_icon;
        }

        $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
        $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(3)->limit(3)->pluck('cost');
        $count = count($lastSx);
        $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
        if ($sx != 0) {
            $finalData['pert_diff'] = number_format((float) $lastSx / $sx * 100, 2, '.', '');
        } else {
            $finalData['pert_diff'] = 0;
        }
        $finalData['sx_value'] = number_format((float) $sx, 2, '.', '');
        $finalData['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');

        return view('frontend.stoxticker-details-data', compact('finalData'));
    }

}
