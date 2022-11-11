<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;

class OptimizeMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Downloads images from S3, converts them to WebP, and uploads them again. Videos: WIP';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $imagesToBeOptimized = Media::where([["optimized"=>null],["type"=>0]])->limit(20)->get();
        foreach($imagesToBeOptimized as $im){ 
            $inputpath = storage_path('app/public/tmp/image');
            $f = fopen($inputpath,"w");
            fwrite($f,file_get_contents($im->url)); // Download image from S3 to local
            fclose($f);
            $webp = Webp::make($inputpath); // convert to Webp
            $outputpath = storage_path('app/public/tmp/image.webp');
            if ($webp->save($outputpath)) {
                // File is saved successfully, now upload to S3
                $uploadpath = 'media/'.$im->hash. '/media.webp';
                Storage::disk('s3')->put($uploadpath, file_get_contents($outputpath));          
                $im->optimized = true;
                $im->save();          
            }
            else throw new \Exception("Could not convert image to $outputpath");
        }
        return 0;
    }
}
