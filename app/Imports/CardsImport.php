<?php

namespace App\Imports;

use App\Models\Card;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Collection;
use App\Models\ExcelUploads;

class CardsImport implements ToCollection, WithStartRow
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
            $card = Card::create([
                        'row_id' => ++$this->row,
                        'excel_uploads_id' => $eu_ids->id,
                        'player' => $row[0],
                        'year' => (int) $row[1],
                        'brand' => $row[2],
                        'card' => $row[3],
                        'rc' => ($row[4] == 'RC') ? 'yes' : 'no',
                        'variation' => $row[5],
                        'grade' => $row[6],
                        'sport' => $row[7],
                        'qualifiers' => $row[8],
                        'image' => $row[12],
                        'title' => $row[1] . ' ' . $row[2] . ' ' . $row[0] . ' - #' . $row[3] . ' - ' . (($row[4] == 'RC') ? 'Rookie' : '') . ' ' . $row[5] . ' ' . $row[6],
            ]);
            
            $card->details()->create([
                'season' => $row[9],
                'series' => $row[10],
                'era' => $row[11],
            ]);
            
            $card->player_details()->create([
                'team' => $row[13]
            ]);
        }
    }
}
