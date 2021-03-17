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

class ExcelImports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // try{
            // $file = Storage::disk('public')->get($this->file);
            // sleep(60);
            Excel::import(new ListingsImport, storage_path('app') . '/' . $this->file);
            // $file->delete();
        // }catch(Exception $e){
            // \Log::error($e);
        // }
    }
}
