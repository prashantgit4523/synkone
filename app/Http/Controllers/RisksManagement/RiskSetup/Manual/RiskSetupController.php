<?php

namespace App\Http\Controllers\RisksManagement\RiskSetup\Manual;

use App\Exports\RiskManagement\RiskImportTemplate;
use App\Http\Controllers\Controller;
use App\Imports\RiskManagement\RiskImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use Illuminate\Support\Facades\Session;
use App\Models\RiskManagement\RiskCategory;
use Inertia\Inertia;


class RiskSetupController extends Controller
{

    public function index()
    {
        $riskLikelihoods = RiskMatrixLikelihood::all();
        $riskImpacts = RiskMatrixImpact::all();
        $riskCategories = RiskCategory::all();

        $data = [
            'risksAffectedProperties' => [
                'common' => ['Confidentiality', 'Integrity', 'Availability'],
                'Other' => ['Change Management', 'Ethical', 'Financial', 'Financial Reporting', 'Fraud', 'Geographic', 'IT Operations', 'Logical Access', 'Material Misstatement', 'Operational', 'Privacy', 'Regulatory / Compliance', 'Reputational', 'Strategy']
            ],
            'riskLikelihoods' => $riskLikelihoods,
            'riskImpacts' => $riskImpacts,
            'riskCategories' =>  $riskCategories
        ];

        return Inertia::render('risk-management/risk-setup/manual/ManualRiskSetup',$data);
    }
    
    public function downloadTemplateFile()
    {
        return Excel::download(new RiskImportTemplate(), 'risk-setup.csv');
    }

    public function risksImport(Request $request)
    {
        $request->validate([
            'csv_upload' => 'required|mimes:csv,txt',
        ], [
            'csv_upload.required' => 'The CSV upload field is required',
            'csv_upload.mimes' => 'CSV format error',
        ]);

        if(!$request->hasFile('csv_upload'))
        {
            return redirect()->back()->with('error', 'The CSV upload field is required')->with('activeTab','RiskSetup');
        }
        else
        {
            $controlsCsvfile = $request->file('csv_upload');

            $file_data = file_get_contents($controlsCsvfile);

            /* When file encoding is not UTF-8.  Converting file encoding to utf-8 and rewriting the same file */
            if (!mb_check_encoding($file_data, 'UTF-8')) {
                $utf8_file_data = utf8_encode($file_data);

                file_put_contents($controlsCsvfile, $utf8_file_data);
            }
            $import = new RiskImport($request);

            Excel::import($import, $controlsCsvfile);

            $messages = $import->msgBag->getMessages();

            if (isset($messages['csv_upload_error'])) {
                return redirect()->back()->with('error',$messages['csv_upload_error'][0])->with('activeTab','RiskSetup');
            }else{
                return redirect()->back()->with('success', 'All rows successfully inserted')->with('activeTab','RiskSetup');
            }
        }
    }
}
