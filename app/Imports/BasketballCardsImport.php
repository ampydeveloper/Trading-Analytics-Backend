<?php

namespace App\Imports;

use App\Models\Card;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class BasketballCardsImport implements ToModel, WithStartRow
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
    public function model(array $row)
    {
        return new Card([
            'row_id' => ++$this->row,
            'sport' => 'basketball',
            'player' => $row[0],
            'year' => (int) $row[1],
            'brand' => $row[2],
            'card' => $row[3],
            'rc' => ($row[4] == 'rc') ? 0 : 1,
            'variation' => $row[5],
            'grade' => $row[6],
            'qualifiers' => $row[7],
            'qualifiers2' => $row[8],
            'qualifiers3' => $row[9],
            'qualifiers4' => $row[10],
            'qualifiers5' => $row[11],
            'qualifiers6' => $row[12],
            'qualifiers7' => $row[13],
            'qualifiers8' => $row[14],
            'title' => $row[1].' '.$row[2].' '.$row[0].' - #'.$row[3].' - '.(($row[4] == 'rc') ? 'Rookie' : '').' '.$row[5].' '.$row[6],
        ]);
    }
}
