<?php

namespace App\Import;

use App\Models\Card;
use Maatwebsite\Excel\Concerns\FromCollection;

class CardExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Card::all();
    }
}
