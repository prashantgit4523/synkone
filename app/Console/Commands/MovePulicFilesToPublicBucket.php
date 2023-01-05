<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GlobalSettings\GlobalSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MovePulicFilesToPublicBucket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfer_file:public';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transferring file to public bucket.';

    protected $temp_folder = '/storage/app/public/tmp-files/';

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
        $global_setting=GlobalSetting::first();

        $file=$this->createFileObject($global_setting->company_logo);
        if($file){
            $this->info("Moving logo of tenant:".tenant('id'));
            $request=new Request();
            $request->files->set('company_logo', $file);
            $new_path=$request->file('company_logo')->store(
                tenant('id').'/public/global_settings/1','s3-public'
            );
            // fix to revert
            $global_setting->company_logo=$new_path;
            $global_setting->save();
            $this->comment('Success!!');
        }
        else{
            $this->info('Default logo doing nothing for tenant:'.tenant('id'));
        }
        
        return 0;
    }

     /**
     * Create file object from url.
     *
     * @var array
     */
    public function createFileObject($url){
  
        $path_parts = pathinfo($url);
        if($path_parts['dirname'] != 'assets/images'){
            $newPath= base_path().$this->temp_folder;

            if(!is_dir ($newPath)){
                mkdir($newPath, 0777);
            }
    
            $file_name=explode('?X-Amz-Content-Sha256',$path_parts['basename'])[0];
            $newUrl = $newPath . $file_name;
            copy($url, $newUrl);
            $imgInfo = getimagesize($newUrl);
            
            if($imgInfo){
                return new UploadedFile(
                    $newUrl,
                    $file_name,
                    $imgInfo['mime'],
                    filesize($newUrl),
                    true,
                    TRUE
                );
            }
            else{
                return new UploadedFile(
                    $newUrl,
                    $file_name,
                    'svg',
                    filesize($newUrl),
                    true,
                    TRUE
                );
            }
            
      
        }

        return null;
    }
}
