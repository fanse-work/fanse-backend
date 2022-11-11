<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Media;
use File;
use Webp;
use UploadedFile;

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
            $inputpath = storage_path('app/public/tmp/image');
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
     * @static
     * @param     string $path
     * @param     bool $public default false
     * @return    object(Symfony\Component\HttpFoundation\File\UploadedFile)
     * @author    Alexandre Thebaldi
     */

    protected static function pathToUploadedFile( $path, $public = false )
    {
        $name = File::name( $path );

        $extension = File::extension( $path );

        $originalName = $name . '.' . $extension;

        $mimeType = File::mimeType( $path );

        $size = File::size( $path );

        $error = null;

        $test = $public;

        $object = new UploadedFile( $path, $originalName, $mimeType, $size, $error, $test );

        return $object;
    }
}
