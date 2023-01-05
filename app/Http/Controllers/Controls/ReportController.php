<?php

namespace App\Http\Controllers\Controls;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Report\Report;
use App\Models\ReportCategory;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class ReportController extends Controller
{

   public function reportData(){
        $report = Report::first();
        
        if(!$report){
            $shareLink = $this->generateShareLink();

            $report = Report::create([
                'name' => 'Securiyt Report',
                'share_link' => $shareLink,
                'status' => 0
            ]);
        }

        return $report;
   }

    public function view(){
        $authCheck = auth()->check();
        
        return Inertia::render('report/SharedReport', compact('authCheck'));
    }

    public function categoryData(Request $request){
        if(env('TENANCY_ENABLED')){
            Artisan::call('tenants:run security_report:check --argument="--data_scope='.$request->data_scope.'" --tenants='.tenant('id'));
        }else{
            Artisan::call('security_report:check --data_scope='.$request->data_scope);
        }

        return ReportCategory::with('controls')->get();
    }

    public function sharedView(Request $request){
        if(env('TENANCY_ENABLED')){
            Artisan::call('tenants:run security_report:check --tenants='.tenant('id'));
        }else{
            Artisan::call('security_report:check');
        }

        $report = Report::first();
        
        if(!$report->status){
            abort(404);
        }
        
        if($request->fullUrl() !== $report->share_link){
            abort(404);
        }

        $categories = ReportCategory::with('controls')->get();
        $authCheck = auth()->check();

        return Inertia::render('report/SharedReport', compact('categories','authCheck'));
    }

    public function update(Request $request){
        try{
            $report = Report::first();

            $report->update([
                'share_link' => $report->temp_share_link ?? $report->share_link,
                'status' => (int) $request->status
            ]);
    
            return redirect()->back()->withSuccess('Report sharing data updated successfully.');
        }catch(\Exception $e){
            return redirect()->back()->withError('Failed to update report sharing data.');
        }
    }

    public function regenerateShareLink(){
        try{
            $report = Report::first();

            $tempUrl = $this->generateShareLink();

            $report->update([
                'temp_share_link' => $tempUrl,
                'share_link' => $tempUrl
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New URL generated successfully.',
                'link' => $tempUrl
            ]);
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Couldn\'t generate new url. Try again later.'
            ]);
        }
    }

    private function generateShareLink(){
        $tempUrl = URL::route('report.sharedView');
        $tempUrl .= '?signature='. hash_hmac('sha256', $tempUrl.tenant('id').time(),tenant('id'));

        return $tempUrl;
    }
}
