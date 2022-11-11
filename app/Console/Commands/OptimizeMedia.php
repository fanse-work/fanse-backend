<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Webp;
use Storage;

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

        $imagesToBeOptimized = Media::where([["optimized","=",null],
                                            ["type","=",0],
                                            ["status","=",Media::STATUS_ACTIVE]])
                                            ->limit(20)->get();

        foreach($imagesToBeOptimized as $im){ 

            if(!in_array(strtolower($im->extension),["jpg","jpeg","png","tiff","tif"])){  // check if extension can be converted to Webp    
                $im->optimized = true;
                $im->save();          
                continue;
            }

            $inputpath = storage_path("app/public/tmp/image.$im->extension");
            $f = fopen($inputpath,"w");
            fwrite($f,file_get_contents($im->url)); // Download image from S3 to local
            fclose($f);
            $uploadedFile = static::pathToUploadedFile($inputpath);

            $webp = Webp::make($uploadedFile); // convert to Webp
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

    /**
     * Create an UploadedFile object from absolute path 
     *
     * @param     string $path
     * @param     bool $test default true
     * @return    object(Illuminate\Http\UploadedFile)
     * 
     * Based of Alexandre Thebaldi answer here:
     * https://stackoverflow.com/a/32258317/6411540
     */
    protected function pathToUploadedFile( $path, $test = true ) {
        $filesystem = new Filesystem;
        
        $name = $filesystem->name( $path );
        $extension = $filesystem->extension( $path );
        $originalName = $name . '.' . $extension;
        $mimeType = $filesystem->mimeType( $path );
        $error = null;

        return new UploadedFile( $path, $originalName, $mimeType, $error, $test );
    }

}
