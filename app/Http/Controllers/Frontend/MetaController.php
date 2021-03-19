<?php

//namespace App\Http\Controllers\Api;
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;

use App\Imports\CardsImport;
use App\Imports\ListingsImport;
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
use App\Models\RequestSlab;
use App\Models\RequestListing;
use App\Models\ExcelUploads;
use Carbon\Carbon;
use Excel;
use Validator;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Facades\DB;
use App\Jobs\ExcelImports;

class MeatController extends Controller {

    public function cardData(Request $request) {
         return view('frontend.meta');
    }
}