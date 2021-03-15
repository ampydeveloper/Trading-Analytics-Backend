<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;

use App\Models\Card;
use App\Models\CardValues;
use App\Models\Ebay\EbayItems;
use App\Models\Ebay\EbayItemSellingStatusHistory;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\SeekException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\TransferException;
use Log;
use Illuminate\Http\Request;

/**
 * Class DashboardController.
 */
class DashboardController extends Controller
{
    /**
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // $this->test($request);
        // $this->sampleCron();
        // $this->filtersCards();
        return view('backend.dashboard');

    }

    public function test($request)
    {
        $page = $request->input('page', 1);
        $take = $request->input('take', 5000);
        $skip = $take * $page;
        $skip = $skip - $take;
        $data = EbayItems::get()->skip($skip)->take($take);;
        foreach ($data as $key => $ebay) {
            $today = Carbon::now()->format('Y-m-d');
            if(CardValues::where(['card_id' => $ebay['card_id'], 'date' => $today])->doesntExist()){
                $avg_value = EbayItemSellingStatusHistory::where('itemId', $ebay['itemId'])->orderBy('created_at', 'DESC')->limit(3)->avg('currentPrice');
                $cardVal = new CardValues();
                $cardVal->card_id = $ebay['card_id'];
                $cardVal->date = $today;
                $cardVal->avg_value = $avg_value;
                if(!$cardVal->save()){
                    throw new Exception("Error Processing Request on creating updating card avg price for item id" . $ebay['itemId'], 1);
                }
            }
        }
    }

    public function sampleCron()
    {
        $card = Card::first();
        //dump($card->toArray());
        $keyword = $card->player . " ";
        $keyword .= $card->year . " ";
        $keyword .= $card->brand . " ";
        if ($card->rc == 'yes') {
            $keyword .= "RC ";
        }
        $keyword .= $card->grade . " ";
        $keyword .= $card->qualifiers . " ";

        //dump($keyword);
        $operation = 'findItemsByKeywords';
        $pageNumber = 1;

        try {
            $client = new Client();


            $url = "https://open.api.ebay.com/shopping?";
            $url .= "callname=GetSingleItem";
            $url .= "&responseencoding=XML";
            $url .= "&appid=" . env('EBAY_APP_ID', '');
            $url .= "&siteid=0";
            $url .= "&version=967";
            $url .= "&IncludeSelector=ItemSpecifics,Details,BuyItNowPrice";
            $url .= "&ItemID=303632767021";


            //    $url = "https://svcs.ebay.com/services/search/FindingService/v1?";
            //    $url .= "OPERATION-NAME=" . $operation;
            //    $url .= "&SERVICE-VERSION=1.0.0";
            //    $url .= "&SECURITY-APPNAME=" . env('EBAY_APP_ID', '');
            //    $url .= "&RESPONSE-DATA-FORMAT=XML";
            //    $url .= "&REST-PAYLOAD";
            //    $url .= "&keywords=" . $keyword;
            //    $url .= "&outputSelector(0)=AspectHistogram";
            //    $url .= "&outputSelector(1)=StoreInfo";
            //    $url .= "&outputSelector(2)=GalleryInfo";
            //    $url .= "&outputSelector(3)=UnitPriceInfo";
            //    $url .= "&outputSelector(4)=SellerInfo";
            //    $url .= "&outputSelector(5)=PictureURLLarge";
            //    $url .= "&outputSelector(6)=PictureURLSuperSize";
            //    $url .= "&outputSelector(7)=ItemSpecifics";
            //    $url .= "&paginationInput.pageNumber=" . $pageNumber;


            $response = $client->request('get', $url);
            $xml = simplexml_load_string($response->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);
            $result = json_decode(json_encode($xml), true);
            dd($result);
            if ($result['ack'] == 'Success') {
                return [
                    'data' => $result['searchResult'],
                    'pagination' => $result['paginationOutput'],
                ];
            } else {
                Log::error('EbayService ack ' . $result['ack'] . ',Message ' . $result['errorMessage']['error']['message']);
                return [
                    'error' => $result['ack'],
                    'errorMessage' => $result['errorMessage'],
                ];
            }
        } catch (RequestException $exception) {
            dd($exception);
            Log::error('EbayService RequestException ' . $exception->getMessage());
            return ['error' => 'RequestException ', 'code' => $exception->getCode()];
        } catch (BadResponseException $exception) {
            dd($exception);
            Log::error('EbayService BadResponseException ' . $exception->getMessage());
            return ['error' => 'BadResponseException ', 'code' => $exception->getCode()];
        } catch (ClientException $exception) {
            dd($exception);
            Log::error('EbayService ClientException ' . $exception->getMessage());
            return ['error' => 'ClientException ', 'code' => $exception->getCode()];
        } catch (ConnectException $exception) {
            dd($exception);
            Log::error('EbayService ConnectException ' . $exception->getMessage());
            return ['error' => 'ConnectException ', 'code' => $exception->getCode()];
        } catch (GuzzleException $exception) {
            dd($exception);
            Log::error('EbayService GuzzleException ' . $exception->getMessage());
            return ['error' => 'GuzzleException ', 'code' => $exception->getCode()];
        } catch (SeekException $exception) {
            dd($exception);
            Log::error('EbayService SeekException ' . $exception->getMessage());
            return ['error' => 'SeekException ', 'code' => $exception->getCode()];
        } catch (ServerException $exception) {
            dd($exception);
            Log::error('EbayService ServerException ' . $exception->getMessage());
            return ['error' => 'ServerException ', 'code' => $exception->getCode()];
        } catch (TooManyRedirectsException $exception) {
            dd($exception);
            Log::error('EbayService TooManyRedirectsException ' . $exception->getMessage());
            return ['error' => 'TooManyRedirectsException ', 'code' => $exception->getCode()];
        } catch (TransferException $exception) {
            dd($exception);
            Log::error('EbayService TransferException ' . $exception->getMessage());
            return ['error' => 'TransferException ', 'code' => $exception->getCode()];
        } catch (Exception $exception) {
            dd($exception);
            Log::error('EbayService GeneralException ' . $exception->getMessage());
            return ['error' => 'GeneralException ', 'code' => $exception->getCode()];
        }
    }

    public function filtersCards()
    {

        echo "<code><pre>";
        $ebayItems = EbayItems::where('card_id', 56)->with('card', 'specifications')->get();
        $advanceSearchData = [];
        foreach ($ebayItems as $key => $item) {
            foreach ($item->specifications as $key2 => $spec) {
                $index = str_replace(array('/', ' '), array('', ''), strtolower($spec['name']));
                if (!isset($advanceSearchData[$index])) {
                    $advanceSearchData[$index] = [];
                }
                if (!in_array($spec['value'], $advanceSearchData[$index])) {
                    $advanceSearchData[$index][] = $spec['value'];
                }
            }
        }
        print_r($advanceSearchData);
        echo "</code></pre>";
    }
}
