<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

use Excel;
use App\Imports\ListingsImport;
use App\Imports\CardsImport;

class ExcelImports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file;
    protected $type;
    protected $filename;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->file = $data['file'];
        $this->type = $data['type'];
        $this->filename = $data['filename'];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
//        var_dump('edd');
        try{
//            $filename = explode('/', $this->file->getClientOriginalName());
//            $filename = $filename[count($filename)-1];
            if($this->type == 'listings'){
                Excel::import(new ListingsImport($this->filename), storage_path('app') . '/' . $this->file);
            }else if($this->type == 'slabs'){
                Excel::import(new CardsImport($this->filename), storage_path('app') . '/' . $this->file);
            }
            Storage::disk('local')->delete($this->file);

        }catch(Exception $e){            
//            \Log::error($e);
        }
    }
}
