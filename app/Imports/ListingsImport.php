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

                $cat = array(
                    'football' => '1',
                    'baseball' => '2',
                    'basketball' => '3',
                    'soccer' => '4',
                    'pokemon' => '10',
                );
                $cat_id = $cat[$row[4]];

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
                                'timeLeft' => isset($data['timeLeft']) ? $data['timeLeft'] : null,
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
                                'startTime' => isset($row[5]) ? Carbon::create($row[5])->format('Y-m-d h:i:s') : null,
                                'endTime' => isset($row[6]) ? Carbon::create($row[6])->format('Y-m-d h:i:s') : null,
                    ]);

                    EbayItems::create([
                        'card_id' => $row[1],
                        'excel_uploads_id' => $eu_ids->id,
                        'itemId' => $data['ebay_id'],
                        'title' => $data['name'],
                        'category_id' => $cat_id,
                        'globalId' => isset($data['details']['Site']) ? 'EBAY-' . $data['details']['Site'] : null,
                        'galleryURL' => isset($data['image']) ? $data['image'] : null,
                        'viewItemURL' => isset($data['details']['ViewItemURLForNaturalSearch']) ? $data['details']['ViewItemURLForNaturalSearch'] : null,
                        'autoPay' => isset($data['details']['AutoPay']) ? $data['details']['AutoPay'] : null,
                        'postalCode' => isset($data['details']['PostalCode']) ? $data['details']['PostalCode'] : null,
                        'location' => isset($data['location']) ? $data['location'] : null,
                        'country' => isset($data['details']['Country']) ? $data['details']['Country'] : null,
                        'returnsAccepted' => isset($data['returns']) == 'ReturnsNotAccepted' ? false : true,
                        'condition_id' => isset($data['details']['ConditionID']) ? $data['details']['ConditionID'] : 1,
                        'pictureURLLarge' => isset($data['image']) ? $data['image'] : null,
                        'pictureURLSuperSize' => isset($data['image']) ? $data['image'] : null,
                        'listing_ending_at' => isset($data['timeLeft']) ? $data['timeLeft'] : null,
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
