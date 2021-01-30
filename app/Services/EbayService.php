<?php

namespace App\Services;

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
use Carbon\Carbon;

class EbayService
{
    protected $creds;
    protected $domain;

    public function __construct()
    {
        $this->creds = base64_encode(env('EBAY_APP_ID_SANDBOX') . ':' . env('EBAY_CLIENT_SECRET_SANDBOX'));
        $this->domain = 'https://api.sandbox.ebay.com/';
        if (env('EBAY_ENVIRONMENT') == 'production') {
            $this->creds = base64_encode(env('EBAY_APP_ID') . ':' . env('EBAY_CLIENT_SECRET'));
            $this->domain = 'https://api.ebay.com/';
        }
    }

    private static function _guzzelRequest($url)
    {
        try {
            $client = new Client();
            $response = $client->request('get', $url);
            $xml = simplexml_load_string($response->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);
            return json_decode(json_encode($xml), true);
        } catch (RequestException $exception) {
            Log::error('EbayService RequestException ' . $exception->getMessage());
            return ['error' => 'RequestException ', 'code' => $exception->getCode()];
        } catch (BadResponseException $exception) {
            Log::error('EbayService BadResponseException ' . $exception->getMessage());
            return ['error' => 'BadResponseException ', 'code' => $exception->getCode()];
        } catch (ClientException $exception) {
            Log::error('EbayService ClientException ' . $exception->getMessage());
            return ['error' => 'ClientException ', 'code' => $exception->getCode()];
        } catch (ConnectException $exception) {
            Log::error('EbayService ConnectException ' . $exception->getMessage());
            return ['error' => 'ConnectException ', 'code' => $exception->getCode()];
        } catch (GuzzleException $exception) {
            Log::error('EbayService GuzzleException ' . $exception->getMessage());
            return ['error' => 'GuzzleException ', 'code' => $exception->getCode()];
        } catch (SeekException $exception) {
            Log::error('EbayService SeekException ' . $exception->getMessage());
            return ['error' => 'SeekException ', 'code' => $exception->getCode()];
        } catch (ServerException $exception) {
            Log::error('EbayService ServerException ' . $exception->getMessage());
            return ['error' => 'ServerException ', 'code' => $exception->getCode()];
        } catch (TooManyRedirectsException $exception) {
            Log::error('EbayService TooManyRedirectsException ' . $exception->getMessage());
            return ['error' => 'TooManyRedirectsException ', 'code' => $exception->getCode()];
        } catch (TransferException $exception) {
            Log::error('EbayService TransferException ' . $exception->getMessage());
            return ['error' => 'TransferException ', 'code' => $exception->getCode()];
        } catch (Exception $exception) {
            Log::error('EbayService GeneralException ' . $exception->getMessage());
            return ['error' => 'GeneralException ', 'code' => $exception->getCode()];
        }
    }



    public static function findItemsByKeywords($keyword = '', $pageNumber)
    {
        try {
            $url = "https://svcs.ebay.com/services/search/FindingService/v1?";
            $url .= "OPERATION-NAME=findItemsByKeywords";
            $url .= "&SERVICE-VERSION=1.0.0";
            $url .= "&SECURITY-APPNAME=" . env('EBAY_APP_ID', '');
            $url .= "&RESPONSE-DATA-FORMAT=XML";
            $url .= "&REST-PAYLOAD";
            $url .= "&keywords=" . $keyword;
            $url .= "&outputSelector(0)=SellerInfo";
            $url .= "&outputSelector(1)=StoreInfo";
            $url .= "&outputSelector(2)=GalleryInfo";
            $url .= "&outputSelector(3)=UnitPriceInfo";
            $url .= "&outputSelector(4)=AspectHistogram";
            $url .= "&outputSelector(5)=PictureURLLarge";
            $url .= "&outputSelector(6)=PictureURLSuperSize";
            $url .= "&paginationInput.pageNumber=" . $pageNumber;
            $result = self::_guzzelRequest($url);
            if (isset($result['error'])) {
                return $result;
            } else {
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
            }
        } catch (Exception $exception) {
            Log::error('EbayService  findItemsByKeywords' . $exception->getMessage());
            return ['error' => 'findItemsByKeywords function ', 'code' => $exception->getCode()];
        }
    }

    public static function getSingleItemDetails($itemid)
    {
        try {
            $url = "https://open.api.ebay.com/shopping?";
            $url .= "callname=GetSingleItem";
            $url .= "&responseencoding=XML";
            $url .= "&appid=" . env('EBAY_APP_ID', '');
            $url .= "&siteid=0";
            $url .= "&version=967";
            $url .= "&IncludeSelector=ItemSpecifics,Details";
            $url .= "&ItemID=" . $itemid;
            $result = self::_guzzelRequest($url);
            if (isset($result['error'])) {
                return $result;
            } else {
                if ($result['Ack'] == 'Success') {
                    return [
                        'data' => $result['Item'],
                    ];
                } else {
                    Log::error('EbayService ack ' . $result['ack'] . ',Message ' . $result['errorMessage']['error']['message']);
                    return [
                        'error' => $result['ack'],
                        'errorMessage' => $result['errorMessage'],
                    ];
                }
            }
        } catch (Exception $exception) {
            Log::error('EbayService  findItemsByKeywords' . $exception->getMessage());
            return ['error' => 'findItemsByKeywords function ', 'code' => $exception->getCode()];
        }
    }

    private function __getOAuthToken($scope = null)
    {
        try {
            $client = new Client(['base_uri' => $this->domain]);
            $data = [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $this->creds),
                ]
            ];
            if ($scope != null) {
                $data['form_parms']['scopes'] = $scope;
            }
            $response = $client->post('/identity/v1/oauth2/token', $data);
            $response = json_decode($response->getBody()->getContents(), true);

            if (array_key_exists('error', $response)) {
                return ['error' => $response['error_description']];
            }
            return ['token' => $response['access_token']];
        } catch (Exception $exception) {
            Log::error('EbayService getOAuthTOken' . $exception->getMessage());
            return ['error' => 'getOAuthTOken function ', 'code' => $exception->getCode()];
        }
    }

    public static function getItemsUpdateFeed()
    {
        $obj = new EbayService();
        $snapshotDate = Carbon::now()->subHours(2)->format('Y-m-d\TH') . ':00:00.000Z';
        $response = $obj->__getOAuthToken(urlencode('https://api.ebay.com/oauth/api_scope/buy.item.feed'));
        if (isset($response['error'])) {
            return $response['error'];
        }
        $token = $response['token'];

        // Get Item Snapshot feed
        $client = new Client(['base_uri' => $obj->domain]);
        $response = $client->get(
            'buy/feed/v1_beta/item_snapshot',
            [
                'query' => [
                    'category_id' => 212,
                    'snapshot_date' => $snapshotDate
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ]
            ]
        );

        dd($response->getBody());
    }

    public static function getItemsSalesInfo()
    {
        $obj = new EbayService();
        $response = $obj->__getOAuthToken(urlencode('https://api.ebay.com/oauth/api_scope/buy.marketplace.insights'));
        if (isset($response['error'])) {
            return $response['error'];
        }
        $token = $response['token'];

        // Get Item Sales feed
        $client = new Client(['base_uri' => $obj->domain]);
        $response = $client->get(
            '/item_sales/search',
            [
                'debug' => true,
                'query' => [
                    'epid' => 392932886978,
                    'category_ids' => 183444
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Charset' => 'utf-8',
                    'Accept-Encoding' => 'application/gzip',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'
                ]
            ]
        );
        dd($response->getBody()->getContents());
    }

    public static function getItemAffiliateWebUrl($epid)
    {
        $obj = new EbayService();
        $response = $obj->__getOAuthToken(urlencode('https://api.ebay.com/oauth/api_scope'));
        if (isset($response['error'])) {
            Log::debug($response['error']);
        }
        $token = $response['token'];


        // Get Item Sales feed
        $client = new Client(['base_uri' => $obj->domain]);
        $response = $client->get(
            '/buy/browse/v1/item_summary/search',
            [
                'debug' => true,
                'query' => [
                    'q' => 'iphone',
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'
                ]
            ]
        );
        dd($response->getBody()->getContents());
    }

    public static function placeBid($item_id, $price)
    {
        $obj = new EbayService();
        $response = $obj->__getOAuthToken(urlencode('https://api.ebay.com/oauth/api_scope/buy.offer.auction'));
        if (isset($response['error'])) {
            Log::debug($response['error']);
        }
        $token = $response['token'];


        // Get Item Sales feed
        $client = new Client(['base_uri' => $obj->domain]);
        $response = $client->get(
            '/buy/offer/v1_beta/bidding/' . $item_id . '/place_proxy_bid',
            [
                'debug' => true,
                'query' => [
                    'maxAmount' => [
                        'currency' => 'USD',
                        'value' => $price
                    ],
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'
                ]
            ]
        );
        dd($response->getBody()->getContents());
    }

    public static function initiateGuestCheckoutSession()
    {
        $obj = new EbayService();
        $response = $obj->__getOAuthToken(urlencode('https://api.ebay.com/oauth/api_scope/buy.guest.order'));
        if (isset($response['error'])) {
            Log::debug($response['error']);
        }
        $token = $response['token'];


        // Get Item Sales feed
        $client = new Client(['base_uri' => $obj->domain]);
        $response = $client->get(
            '/buy/order/v1/guest_checkout_session/initiate',
            [
                'debug' => true,
                'query' => [
                    /* CreateGuestCheckoutSessionRequest */
                    "contactEmail" => "string",
                    "contactFirstName" => "string",
                    "contactLastName" => "string",
                    "creditCard" => [ /* CreditCard */
                        "accountHolderName" => "string",
                        "billingAddress" => [ /* BillingAddress */
                            "addressLine1" => "string",
                            "addressLine2" => "string",
                            "city" => "string",
                            "country" => "CountryCodeEnum => [AD,AE,AF...]",
                            "county" => "string",
                            "firstName" => "string",
                            "lastName" => "string",
                            "postalCode" => "string",
                            "stateOrProvince" => "string"
                        ],
                        "brand" => "string",
                        "cardNumber" => "string",
                        "cvvNumber" => "string",
                        "expireMonth" => "integer",
                        "expireYear" => "integer"
                    ],
                    "lineItemInputs" => [
                        [ /* LineItemInput */
                            "itemId" => "string",
                            "quantity" => "integer"
                        ]
                    ],
                    "shippingAddress" => [ /* ShippingAddress */
                        "addressLine1" => "string",
                        "addressLine2" => "string",
                        "city" => "string",
                        "country" => "CountryCodeEnum => [AD,AE,AF...]",
                        "county" => "string",
                        "phoneNumber" => "string",
                        "postalCode" => "string",
                        "recipient" => "string",
                        "stateOrProvince" => "string"
                    ]
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'
                ]
            ]
        );
        dd($response->getBody()->getContents());
    }
}
