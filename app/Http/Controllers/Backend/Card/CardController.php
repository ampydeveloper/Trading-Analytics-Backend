<?php

namespace App\Http\Controllers\Backend\Card;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\Request;
use App\Imports\BasketballCardsImport;
use App\Imports\BaseballCardsImport;
use App\Imports\FootballCardsImport;
use App\Imports\SoccerCardsImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Backend\Card\AdminRequest;
use App\Http\Requests\Backend\Card\StoreRequest;
use App\Http\Requests\Backend\Card\UpdateRequest;

class CardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(AdminRequest $resquest)
    {
        return view('backend.card.index');
    }


    public function dataTableList(AdminRequest $request)
    {
        try {
            $search = $request->input('search.value', null);
            $length = $request->input('length', 10);
            $start = $request->input('start', 0);
            $page = ($start == 0) ? 1 : ($start / $length) + 1;
            $order = $request->input('order', []);
            $url = $request->url();

            $cards = Card::get();
            $total = count($cards);
            $lastPage = ceil($total / $length);
            if ($cards->isNotEmpty()) {
                if ($search != null && $search != '') {
                    $cards = $cards->filter(function ($item) use ($search) {
    
                        $playerCheck = (strpos(strtolower($item->player), strtolower($search)) !== false);
                        $yearCheck = (strpos(strtolower($item->year), strtolower($search)) !== false);
                        $brandCheck = (strpos(strtolower($item->brand), strtolower($search)) !== false);
                        $cardCheck = (strpos(strtolower($item->card), strtolower($search)) !== false);
                        $crCheck = (strpos(strtolower($item->cr), strtolower($search)) !== false);
                        $variationCheck = (strpos(strtolower($item->variation), strtolower($search)) !== false);
                        $gradeCheck = (strpos(strtolower($item->grade), strtolower($search)) !== false);
                        $qualifiersCheck = (strpos(strtolower($item->qualifiers), strtolower($search)) !== false);
    
                        return ($playerCheck || $yearCheck || $brandCheck || $cardCheck || $crCheck || $variationCheck || $gradeCheck || $qualifiersCheck);
                    })->values();
    
                    $total = count($cards);
                    $lastPage = ceil($total / $length);
                }

                if (!empty($order)) {
                    $order[0]['column'] = $request->input('columns.' . $order[0]['column'] . '.data', null);

                    $cards = $cards->map(function($item) {
                        $temp = $item;
                        $temp['action'] = ' 
                            <a href="'.route('admin.card.edit', $item->id).'" class="btn btn-info edit-card" data-toggle="tooltip" data-placement="top" title="Edit"><i class="fas fa-edit"></i></a>
                            <button data-url="'.route('admin.card.destroy', $item->id).'" class="btn btn-danger delete-card" data-toggle="tooltip" data-placement="top" title="Delete"><i class="fas fa-trash"></i></button>                    
                        ';
                        return $temp;
                    });

                    if ($order[0]['dir'] == 'asc') {
                        $cards = $cards->sortBy($order[0]['column'])->values()->splice($start)->take($length);
                    } else {
                        $cards = $cards->sortByDesc($order[0]['column'])->values()->splice($start)->take($length);
                    }
                }

            }
            
            return response()->json([
                "current_page" => $page,
                "data" => $cards,
                "from" => $length + 1,
                "last_page" => $lastPage,
                "path" => $url,
                "per_page" => (int)$length,
                "to" => (int)$start,
                "total" => $total,
                "recordsTotal" => $total,
                "recordsFiltered" => $total,
            ], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 400);
        }
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function import(AdminRequest $resquest)
    {
        return view('backend.card.import');
    }

    public function importFromExcel(AdminRequest $resquest)
    {
        try {
            if(request()->file('baseball_excel')) {
                Excel::import(new BaseballCardsImport,request()->file('baseball_excel'));
            }else if (request()->file('basketball_excel')) {
                Excel::import(new BasketballCardsImport,request()->file('basketball_excel'));
            }else if (request()->file('football_excel')) {
                Excel::import(new FootballCardsImport,request()->file('football_excel'));
            }else if (request()->file('soccer_excel')) {
                Excel::import(new SoccerCardsImport,request()->file('soccer_excel'));
            }
            return redirect()->route('admin.card.import')->withFlashSuccess('Card imported successfully');
        } catch (\Exception $e) {
            \Log::error($e);
            return redirect()->route('admin.card.import')->withFlashError($e->getMessage());
        }
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.card.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        try {
            if (Card::create([
                'player' => $request->input('player'),
                'year' => (int)$request->input('year'),
                'brand' => $request->input('brand'),
                'card' => $request->input('card'),
                'rc' => $request->input('rc'),
                'variation' => $request->input('variation'),
                'grade' => $request->input('grade'),
                'qualifiers' => $request->input('qualifiers'),
                'qualifiers2' => $request->input('qualifiers2'),
                'qualifiers3' => $request->input('qualifiers3'),
                'qualifiers4' => $request->input('qualifiers4'),
                'qualifiers5' => $request->input('qualifiers5'),
                'qualifiers6' => $request->input('qualifiers6'),
                'qualifiers7' => $request->input('qualifiers7'),
                'qualifiers8' => $request->input('qualifiers8'),
                'readyforcron' => $request->input('readyforcron')
            ])) {
                return redirect()->route('admin.card.index')->withFlashSuccess('Card updated successfully');
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.card.index')->withErrors($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function show(Card $card)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function edit(Card $card)
    {
        return view('backend.card.edit')
            ->withCard($card);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Card $card)
    {
        try {
            if ($card->update([
                'player' => $request->input('player'),
                'year' => (int)$request->input('year'),
                'brand' => $request->input('brand'),
                'card' => $request->input('card'),
                'rc' => $request->input('rc'),
                'variation' => $request->input('variation'),
                'grade' => $request->input('grade'),
                'qualifiers' => $request->input('qualifiers'),
                'qualifiers2' => $request->input('qualifiers2'),
                'qualifiers3' => $request->input('qualifiers3'),
                'qualifiers4' => $request->input('qualifiers4'),
                'qualifiers5' => $request->input('qualifiers5'),
                'qualifiers6' => $request->input('qualifiers6'),
                'qualifiers7' => $request->input('qualifiers7'),
                'qualifiers8' => $request->input('qualifiers8'),
                'readyforcron' => $request->input('readyforcron')
            ])) {
                return redirect()->route('admin.card.index')->withFlashSuccess('Card updated successfully');
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.card.index')->withErrors($e->getMessage());
        }

        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function destroy(Card $card)
    {
        try {
            if($card->delete()){
                return response()->json('Deleted Successfully', 200);
            }
            return response()->json('Unable to delete card', 500);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
        
    }
}
