<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardValues;
use App\Models\CardSales;
use App\Models\Board;
use App\Models\Ebay\EbayItems;
use Carbon\Carbon;
use Validator;
use Illuminate\Support\Facades\Storage;

class StoxtickerController extends Controller {

    public function slabSearch(Request $request) {
        try {
            $data = Card::where('player', 'like', '%' . $request->input('keyword') . '%')->distinct('player')->where('active', 1)->with('details')->get()->take(12);
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function createBoard(Request $request) {
        try {
            Board::create([
                'name' => $request->input('name'),
//                'sports' => json_encode($request->input('sports')),
                'cards' => json_encode($request->input('cards')),
            ]);
            return response()->json(['status' => 200, 'message' => 'Board Created'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function searchBoard(Request $request) {
        try {
            $boards = Board::where('name', 'like', '%' . $request->input('keyword') . '%')->get()->take(4);
//            foreach($boards as $board){
//                $board->cards
//            }
            $finalData = $this->__cardData();

            return response()->json(['status' => 200, 'data' => $boards, 'card_data' => $finalData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function boardDetails($board) {
        try {
            $board = Board::where('id', $board)->first();
            $all_cards = json_decode($board->cards);
            foreach ($all_cards as $card) {
                $each_cards[] = Card::where('id', (int) $card)->with('details')->first();
            }
            $finalData = $this->__cardData();
            return response()->json(['status' => 200, 'board' => $board, 'cards' => $each_cards, 'card_data' => $finalData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function __cardData() {
        $card_id = 12;
        $days = 2;
        $cvs = CardSales::where('card_id', $card_id)->groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
        $data['values'] = $cvs->pluck('cost')->toArray();
        $data['labels'] = $cvs->pluck('timestamp')->toArray();
        $data['qty'] = $cvs->pluck('quantity')->toArray();

        $data = $this->__groupGraphData($days, $data);
        $data_qty = $this->__groupGraphData($days, ['labels' => $data['values'], 'values' => $data['qty']]);
        if ($days == 2) {
            $labels = [];
            $values = [];
            $qty = [];
            for ($i = 0; $i <= 23; $i++) {
                $labels[] = ($i < 10) ? '0' . $i . ':00' : $i . ':00';
                $values[] = (count($data['values']) > 0 ) ? $data['values'][0] : 0;
                $qty[] = (count($data['qty']) > 0 ) ? $data['qty'][0] : 0;
            }
            $data['labels'] = $labels;
            $data['values'] = $values;
            $data['qty'] = $qty;
        } else {
            $data['values'] = array_reverse($data['values']);
            $data['labels'] = array_reverse($data['labels']);
            $data['qty'] = array_reverse($data['qty']);
        }
        $finalData['values'] = $data['values'];
        $finalData['labels'] = $data['labels'];
        $finalData['qty'] = $data['qty'];
//        $finalData['rank'] = $this->getCardRank($card_id);
        return $finalData;
    }

    public function __groupGraphData($days, $data) {
        $months = null;
        $years = null;
        if ($days > 30 && $days <= 365) {
            $months = (int) ($days / 30);
        }
        if ($days > 365) {
            $years = (int) ($days / 365);
        }

        $grouped = [];
        $max = 0;
        if ($months != null) {
            $max = $months;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt);
                $dt = sprintf('%s-%s', $dt[0], $dt[1]);
                if (!in_array($dt, array_keys($grouped))) {
                    $grouped[$dt] = 0;
                }
                $grouped[$dt] += round($data['values'][$ind]);
            }
        }
        if ($years != null) {
            $max = $years;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt)[0];
                if (!in_array($dt, array_keys($grouped))) {
                    $grouped[$dt] = 0;
                }
                $grouped[$dt] += round($data['values'][$ind]);
            }
        }

        if ($grouped != []) {
            $data['values'] = array_values($grouped);
            $data['labels'] = array_keys($grouped);
            $data['values'] = array_splice($data['values'], 0, $max);
            $data['labels'] = array_splice($data['labels'], 0, $max);
        }
        return $data;
    }

}
