<?php

namespace App\Imports;

use App\Models\Card;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Collection;
use App\Models\CardSales;
use App\Models\Ebay\EbayItems;
use App\Models\Ebay\EbayItemSellerInfo;
use App\Models\Ebay\EbayItemSpecific;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\Ebay\EbayItemListingInfo;
use Carbon\Carbon;
use App\Models\ExcelUploads;

class ListingsImport implements ToCollection, WithStartRow {

    private $row = 1;
    private $filename;

    public function __construct($name) {
        $this->filename = $name;
    }

    /**
     * @return int
     */
    public function startRow(): int
    {
    return 2;
}

/**
 * @param array $row
 *
 * @return \Illuminate\Database\Eloquent\Model|null
 */
public function collection(Collection $rows) {
    $eu_ids = ExcelUploads::create([
                'file_name' => $this->filename,
                'status' => 0,
    ]);

    foreach ($rows as $row) {
        if ($row[8] != 'EBAY') {
            if (!empty($row[1])) {
                $timestamp = date('Y-m-d H:i:s', strtotime($row[6]));
                CardSales::create([
                    'card_id' => (int) $row[1],
                    'excel_uploads_id' => (int) $eu_ids->id,
//                        'timestamp' => \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[6]),
                    'timestamp' => $timestamp,
                    'quantity' => 1,
                    'cost' => str_replace('$', '', $row[3]),
                    'source' => $row[8],
                ]);
            }
        } else if ($row[8] == 'EBAY') {
            if ($row[0] != null || !empty($row[0])) {
                $script_link = '/home/ubuntu/ebay/ebayFetch/bin/python3 /home/ubuntu/ebay/core.py """' . $row[0] . '"""';
                $scrap_response = shell_exec($script_link . " 2>&1");
                $data = (array) json_decode($scrap_response);

                $cat_id = 1;
                if (isset($row[4]) && $row[4] != null) {
                    $cat = array(
                        'football' => '1',
                        'baseball' => '2',
                        'basketball' => '3',
                        'soccer' => '4',
                        'pokemon' => '10',
                    );
                    $cat_id = $cat[$row[4]];
                } else if (isset($data['details']['PrimaryCategoryID'])) {
                    $cat_id = EbayItemCategories::where('categoryId', $data['details']['PrimaryCategoryID'])->first()['id'];
                }

                if (isset($data['details']['PictureURL'])) {
                    if (is_array($data['details']['PictureURL']) && count($data['details']['PictureURL']) > 0) {
                        $pictureURLLarge = $data['details']['PictureURL'][0];
                        $pictureURLSuperSize = $data['details']['PictureURL'][count($data['details']['PictureURL']) - 1];
                    } else {
                        $pictureURLLarge = $data['details']['PictureURL'];
                        $pictureURLSuperSize = $data['details']['PictureURL'];
                    }
                } else if (isset($data['image']) && !empty($data['image'])) {
                    $pictureURLLarge = $data['image'];
                    $pictureURLSuperSize = $data['image'];
                } else {
                    $pictureURLLarge = null;
                    $pictureURLSuperSize = null;
                }

                $auction_end = null;
                if (isset($row[6]) && $row[6] != null) {
                    $auction_end = Carbon::create($row[6])->format('Y-m-d h:i:s');
                } else if (!empty($data['timeLeft'])) {
                    date_default_timezone_set("America/Los_Angeles");
                    $auction_end_str = $data['timeLeft'] / 1000;
                    $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                }

                if (array_key_exists('price', $data) && !empty($data['price']) || !empty($row[3])) {
                    if (!empty($row[3])) {
                        $price = str_replace('$', '', $row[3]);
                    } else if (!empty($data['price'])) {
                        $price = $data['price'];
                    } else {
                        $price = 0;
                    }
                    $selling_status = EbayItemSellingStatus::create([
                                'itemId' => $data['ebay_id'],
                                'currentPrice' => $price,
                                'convertedCurrentPrice' => $price,
                                'sellingState' => $price,
                                'timeLeft' => $auction_end,
                    ]);
                }
                if (array_key_exists('seller', $data) && !empty($data['seller'])) {
                    $data['seller'] = (array) $data['seller'];
                    $seller_info = EbayItemSellerInfo::create([
                                'itemId' => $data['ebay_id'],
                                'sellerUserName' => isset($data['seller']['name']) ? $data['seller']['name'] : null,
                                'positiveFeedbackPercent' => isset($data['seller']['feedback']) ? $data['seller']['feedback'] : null,
                                'seller_contact_link' => isset($data['seller']['contact']) ? $data['seller']['contact'] : null,
                                'seller_store_link' => isset($data['seller']['store']) ? $data['seller']['store'] : null
                    ]);
                }
                if (array_key_exists('specifics', $data) && !empty($data['specifics'])) {
                    foreach ($data['specifics'] as $key => $speci) {
                        if (isset($speci['Value'])) {
                            if ($speci['Value'] != "N/A") {
                                $speci_name = isset($speci['Name']) ? $speci['Name'] : null;
                                $speci_name = str_replace(':', '', $speci_name);
                                EbayItemSpecific::create([
                                    'itemId' => $data['ebay_id'],
                                    'name' => $speci_name,
                                    'value' => is_array($speci['Value']) ? implode(',', $speci['Value']) : $speci['Value']
                                ]);
                            }
                        } else {
                            $speci_name = isset($key) ? $key : null;
                            $speci_name = str_replace(':', '', $speci_name);
                            EbayItemSpecific::create([
                                'itemId' => $data['ebay_id'],
                                'name' => $speci_name,
                                'value' => is_array($speci) ? implode(',', $speci) : $speci
                            ]);
                        }
                    }
                }
                if (array_key_exists('ebay_id', $data)) {
                    $listing_info = EbayItemListingInfo::create([
                                'itemId' => $data['ebay_id'],
                                'buyItNowAvailable' => isset($row[7]) ? $row[7] : null,
                                'listingType' => isset($row[2]) ? $row[2] : null,
                                'startTime' => null,
                                'endTime' => $auction_end,
                    ]);

                    EbayItems::create([
                        'card_id' => $row[1],
                        'excel_uploads_id' => $eu_ids->id,
                        'itemId' => $data['ebay_id'],
                        'title' => $data['name'],
                        'category_id' => $cat_id,
                        'globalId' => isset($data['details']['Site']) ? 'EBAY-' . $data['details']['Site'] : null,
                        'galleryURL' => isset($data['details']['GalleryURL']) ? $data['details']['GalleryURL'] : null,
                        'viewItemURL' => isset($data['details']['ViewItemURLForNaturalSearch']) ? $data['details']['ViewItemURLForNaturalSearch'] : null,
                        'autoPay' => isset($data['details']['AutoPay']) ? $data['details']['AutoPay'] : null,
                        'postalCode' => isset($data['details']['PostalCode']) ? $data['details']['PostalCode'] : null,
                        'location' => isset($data['details']['Location']) ? $data['details']['Location'] : null,
                        'country' => isset($data['details']['Country']) ? $data['details']['Country'] : null,
                        'returnsAccepted' => isset($data['details']['ReturnPolicy']['ReturnsAccepted']) == 'ReturnsNotAccepted' ? false : true,
                        'condition_id' => isset($data['details']['ConditionID']) ? $data['details']['ConditionID'] : 1,
                        'pictureURLLarge' => $pictureURLLarge,
                        'pictureURLSuperSize' => $pictureURLSuperSize,
                        'listing_ending_at' => $auction_end,
                        'is_random_bin' => array_key_exists('random_bin', $data) ? (bool) $data['random_bin'] : 0,
                        'seller_info_id' => isset($seller_info) ? $seller_info->id : null,
                        'selling_status_id' => isset($selling_status) ? $selling_status->id : null,
                        'listing_info_id' => isset($listing_info) ? $listing_info->id : null,
                    ]);
                }
            }
        }
    }

    ExcelUploads::whereId($eu_ids->id)->update(['status' => 1]);
}

}
