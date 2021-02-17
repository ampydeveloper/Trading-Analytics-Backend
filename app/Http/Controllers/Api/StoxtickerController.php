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
            return response()->json(['status' => 200, 'data' => $boards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}