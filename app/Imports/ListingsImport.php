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
use App\Models\Ebay\EbayItemListingInfo;
use Carbon\Carbon;
use App\Models\ExcelUploads;

class ListingsImport implements ToCollection, WithStartRow
{
    private $row = 1;
    
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
    public function collection(Collection $rows)
    {
        
        $eu_ids = ExcelUploads::create([
                'file_name' => substr(md5(mt_rand()), 0, 7),
                'status' => 1,
            ]);
        
        foreach($rows as $row) {
            if($row[8] != 'EBAY'){
                if(!empty($row[1])){
                CardSales::create([
                'card_id' => $row[1],
                'excel_uploads_id' => $eu_ids->id,
                'timestamp' => Carbon::create($row[6])->format('Y-m-d h:i:s'),
                'quantity' => 1,
                'cost' => str_replace('$', '', $row[3]),
                'source' => $row[8],
            ]);
                  }
            }else if($row[8] == 'EBAY'){
                if($row[0]!=null || !empty($row[0])){
                        $script_link = '/home/ubuntu/ebay/ebayFetch/bin/python3 /home/ubuntu/ebay/core.py """'.$row[0].'"""';
                $scrap_response = shell_exec($script_link." 2>&1");
                $data = (array) json_decode($scrap_response);
                
                    $cat = array(
                        'Football'=>'1',
                        'Baseball'=>'2',
                        'Basketball'=>'3',
                        'Soccer'=>'4',
                        'Pokemon'=>'10',
                    );
                        $cat_id = $cat[$row[4]];
                
                EbayItems::create([
                    'card_id' => $row[1],
                    'excel_uploads_id' => $eu_ids->id,
                    'itemId' => $row[0],
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
                    'listing_ending_at' => isset($data['details']['timeLeft']) ? $data['details']['timeLeft'] : null,
                    'is_random_bin' => array_key_exists('random_bin', $data) ? (bool) $data['random_bin'] : 0
                ]);
                if(!empty($data['seller'])){
                   $data['seller'] =  (array) $data['seller'];
                EbayItemSellerInfo::create([
                    'itemId' =>$row[0],
                    'sellerUserName' => isset($data['seller']['name']) ? $data['seller']['name'] : null,
                    'positiveFeedbackPercent' => isset($data['seller']['feedback']) ? $data['seller']['feedback'] : null,
                    'seller_contact_link' => isset($data['seller']['contact']) ? $data['seller']['contact'] : null,
                    'seller_store_link' => isset($data['seller_store_link']) ? $data['seller_store_link'] : null
                ]);
                }
                if(!empty($data['specifics'])){
                foreach ($data['specifics'] as $key=>$speci) {
                    if (isset($speci['Value'])) {
                        if ($speci['Value'] != "N/A") {
                            EbayItemSpecific::create([
                                'itemId' => $row[0],
                                'name' => isset($speci['Name']) ? $speci['Name'] : null,
                                'value' => is_array($speci['Value']) ? implode(',', $speci['Value']) : $speci['Value']
                            ]);
                        }
                    } else {
                        EbayItemSpecific::create([
                            'itemId' => $row[0],
                            'name' => $key,
                            'value' => is_array($speci) ? implode(',', $speci) : $speci
                        ]);
                    }
                }
                }
                EbayItemListingInfo::create([
                    'itemId' => 1,
                    'startTime' => isset($row[5]) ? Carbon::create($row[5])->format('Y-m-d h:i:s') : null,
                    'endTime' => isset($row[5]) ? Carbon::create($row[5])->format('Y-m-d h:i:s') : null,
                ]);
                
                }
            }
            
        }
    }
}