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
//        dump($filename);
//        dump($dir);
//        
//        $files = Storage::disk('public')->allFiles($dir);
////        dump($files);
//        foreach ($files as $file) {
////            dump($file);
//            $path = public_path("storage/$file");
////            dump($path);
//            $explode = explode("/", $file);
////            dump($explode);
//            $newFile = $explode[0] . '/' . $explode[2];
////            dump($newFile);
//            Storage::disk('s3')->put($newFile, file_get_contents($path), 'public');
//        }
////        unlink(public_path("storage/$dir"));
//        Storage::disk('public')->deleteDirectory('storage/'.$dir);
//        dd('end');



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
             $eu_ids = ExcelUploads::create([
                        'file_name' => $this->filename,
                        'status' => 0,
                        'file_type' => 1,
            ]);
             
            $files = Storage::disk('public')->allFiles($this->dir);
            foreach ($files as $file) {
                $path = public_path("storage/$file");
                $explode = explode("/", $file);
                $newFile = $explode[0] . '/' . $explode[2];
                Storage::disk('s3')->put($newFile, file_get_contents($path), 'public');
            }
//            Storage::disk('public')->deleteDirectory('storage/'.$this->dir);
            
            ExcelUploads::whereId($eu_ids->id)->update(['status' => 1]);
            
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
        }
    }
}
