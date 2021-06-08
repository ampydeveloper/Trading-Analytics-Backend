<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class StoreZipImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $filename;
    protected $dir;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filename, $dir)
    {
        $this->filename = $filename;
        $this->dir = $dir;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    
    public function handle()
    {
        try {
            $files = Storage::disk('public')->allFiles($this->dir);
            foreach($files as $file){
                $path = public_path("storage/$file");
                Storage::disk('s3')->put($file,file_get_contents($path), 'public');

                unlink($path);
//                Storage::disk('public')->delete("storage/$file");
            } 
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
        }
    }
}
