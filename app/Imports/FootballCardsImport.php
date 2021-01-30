<?php

namespace App\Imports;

use App\Models\Card;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class FootballCardsImport implements ToModel, WithStartRow
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
            'sport' => 'football',
            'player' => $row[0],
            'year' => (int) $row[1],
            'brand' => $row[2],
            'card' => $row[3],
            'rc' => ($row[4] == 'rc') ? 0 : 1,
            'variation' => $row[5],
            'grade' => $row[6],
            'qualifiers' => $row[7],
            'qualifiers2' => isset($row[8]) ? $row[8] : null,
            'qualifiers3' => isset($row[9]) ? $row[9] : null,
            'qualifiers4' => isset($row[10]) ? $row[10] : null,
            'qualifiers5' => isset($row[11]) ? $row[11] : null,
            'qualifiers6' => isset($row[12]) ? $row[12] : null,
            'qualifiers7' => isset($row[13]) ? $row[13] : null,
            'qualifiers8' => isset($row[14]) ? $row[14] : null,
            'title' => $row[1].' '.$row[2].' '.$row[0].' - #'.$row[3].' - '.(($row[4] == 'rc') ? 'Rookie' : '').' '.$row[5].' '.$row[6],
        ]);
    }
}
