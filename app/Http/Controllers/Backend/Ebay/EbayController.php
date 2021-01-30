<?php

namespace App\Http\Controllers\Backend\Ebay;

use App\Http\Controllers\Controller;
use App\Models\Ebay\EbayItems;
use Illuminate\Http\Request;
use App\Http\Requests\Backend\Card\AdminRequest;

class EbayController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(AdminRequest $resquest)
    {
        return view('backend.ebay.index');
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

            $items = EbayItems::get();
            $total = count($items);
            $lastPage = ceil($total / $length);
            if ($items->isNotEmpty()) {
                if ($search != null && $search != '') {
                    $items = $items->filter(function ($item) use ($search) {
    
                        $itemId = (strpos(strtolower($item->itemId), strtolower($search)) !== false);
                        $title = (strpos(strtolower($item->title), strtolower($search)) !== false);
                        $globalId = (strpos(strtolower($item->globalId), strtolower($search)) !== false);
                        $paymentMethod = (strpos(strtolower($item->paymentMethod), strtolower($search)) !== false);
                        $autoPay = (strpos(strtolower($item->autoPay), strtolower($search)) !== false);
                        $postalCode = (strpos(strtolower($item->postalCode), strtolower($search)) !== false);
                        $location = (strpos(strtolower($item->location), strtolower($search)) !== false);
                        $country = (strpos(strtolower($item->country), strtolower($search)) !== false);
                        $returnsAccepted = (strpos(strtolower($item->returnsAccepted), strtolower($search)) !== false);
                        $isMultiVariationListing = (strpos(strtolower($item->isMultiVariationListing), strtolower($search)) !== false);
                        $topRatedListing = (strpos(strtolower($item->topRatedListing), strtolower($search)) !== false);
                        
    
                        return ($itemId || $title || $globalId || $paymentMethod || $autoPay || $postalCode || $location || $country || $returnsAccepted || $isMultiVariationListing || $topRatedListing);
                    })->values();
    
                    $total = count($items);
                    $lastPage = ceil($total / $length);
                }

                if (!empty($order)) {
                    $order[0]['column'] = $request->input('columns.' . $order[0]['column'] . '.data', null);

                    $items = $items->map(function($item) {
                        $temp = [];
                        $temp['image'] = '<img width="100px" height="150px" src="'.$item['galleryURL'].'" alt="'.$item['title'].'" />';
                        $temp['title'] = '<p class="text-container" title="'.$item['title'].'">'.$item['title'].'</p>';
                        $temp['itemId'] = $item['itemId'];
                        $temp['action'] = '
                            <a href="'.$item['viewItemURL'].'" target="_blank" class="btn btn-primary">Goto Ebay</a>
                            <button data-url="'.route('admin.ebay.destroy', $item->id).'" class="btn btn-danger delete-card" data-toggle="tooltip" data-placement="top" title="Delete"><i class="fas fa-trash"></i></button>                    
                        ';
                        return $temp;
                    });

                    if ($order[0]['dir'] == 'asc') {
                        $items = $items->sortBy($order[0]['column'])->values()->splice($start)->take($length);
                    } else {
                        $items = $items->sortByDesc($order[0]['column'])->values()->splice($start)->take($length);
                    }
                }

            }
            
            return response()->json([
                "current_page" => $page,
                "data" => $items,
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
     * Remove the specified resource from storage.
     *
     * @param  \App\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function destroy(EbayItems $item)
    {
        try {
            if($item->delete()){
                return response()->json('Deleted Successfully', 200);
            }
            return response()->json('Unable to delete Item', 500);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
        
    }
}
