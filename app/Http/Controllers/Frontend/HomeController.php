<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
// use App\Models\MyPortfolio;
use Illuminate\Http\Request;
use App\Services\EbayService;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardValues;
use App\Models\CardSales;
use App\Jobs\ProcessCardsForRetrivingDataFromEbay;
use App\Jobs\ProcessCardsComplieData;
use App\Jobs\CompareEbayImagesWithCardImages;
use App\Models\Ebay\EbayItems;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Jobs\ExcelImports;

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

        return view('frontend.meta', compact('card_details'));
    }

}
