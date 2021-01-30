<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Ebay\EbayItems;
use App\Models\CardPlayerDetails;
use App\Models\CardDetails;
use Log;
use DB;


class ProcessCardsComplieData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $ebayItems = EbayItems::where('card_id',$this->id)->with('card','specifications','sellingStatus')->get();
            $cardsdata = [];
            $price = 0;
            foreach ($ebayItems as $key => $item) {
                if($price == 0){
                    $price = $item->sellingStatus->currentPrice;
                }
                foreach ($item->specifications as $key2 => $spec) {
                    $index = str_replace(array('/', ' '), array('', ''), strtolower($spec['name']));
                    if(!isset($cardsdata[$index])){
                        $cardsdata[$index] = [];
                    }
                    if(!in_array($spec['value'], $cardsdata[$index])){
                        $cardsdata[$index][] = $spec['value'];
                    }
                } 
            }

            CardPlayerDetails::updateOrCreate([
                'card_id' => $this->id,
            ],[
                'name'=> (isset($cardsdata['player'])) ? $cardsdata['player'][0] : ((isset($cardsdata['athlete'])) ? $cardsdata['athlete'][0] : '' ),
                'sports'=> (isset($cardsdata['sports'])) ? $cardsdata['sports'][0] : '',
                'team'=> (isset($cardsdata['team'])) ? $cardsdata['team'][0] : '',
            ]);

            CardDetails::updateOrCreate([
                'card_id' => $this->id,
            ],[
                'number'=> (isset($cardsdata['cardnumber'])) ? $cardsdata['cardnumber'][0] : '',
                'product'=> (isset($cardsdata['product'])) ? $cardsdata['product'][0] : '',
                'season'=> (isset($cardsdata['season'])) ? $cardsdata['season'][0] : '',
                'rookie'=> (isset($cardsdata['cardattributes']) && $cardsdata['cardattributes'][0] == 'Rookie') ? true : false,
                'series'=> (isset($cardsdata['series'])) ? $cardsdata['series'][0] : '',
                'grade'=> (isset($cardsdata['grade'])) ? $cardsdata['grade'][0] : '',
                'manufacturer'=> (isset($cardsdata['cardmanufacturer'])) ? $cardsdata['cardmanufacturer'][0] : '',
                'era'=> (isset($cardsdata['era'])) ? $cardsdata['era'][0] : '',
                'year'=> (isset($cardsdata['year'])) ? $cardsdata['year'][0] : '',
                'grader'=> (isset($cardsdata['grader'])) ? $cardsdata['grader'][0] : '',
                'grader'=> (isset($cardsdata['grader'])) ? $cardsdata['grader'][0] : '',
                'autographed'=> (isset($cardsdata['autographed']) && $cardsdata['autographed'][0] == 'Rookie') ? true : false,
                'brand'=> (isset($cardsdata['brand'])) ? $cardsdata['brand'][0] : '',
                'currentPrice'=> $price,
            ]);
                
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
        }
        
    }
}
