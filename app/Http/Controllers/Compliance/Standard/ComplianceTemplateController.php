<?php

namespace App\Http\Controllers\Compliance\Standard;

use App\Http\Controllers\Controller;
use App\Utils\RegularFunctions;
use App\Models\Compliance\Standard;
use App\Models\Compliance\StandardControl;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComplianceTemplateController extends Controller
{
    protected $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware(function ($request, $next) {
            $this->loggedUser = Auth::guard('admin')->user();

            return $next($request);
        });
    }

    public function view()
    {
        return view('compliance.templates.view');
    }

    public function create()
    {
        $standard = new Standard();
        Log::info('User is attempting to create a new compliance template standard');
        return view('compliance.templates.create', compact('standard'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:190|unique:compliance_standards,name',
            'version' => 'required|max:190',
        ]);

        $input = $request->toArray();
        if (isset($request->dublicateStandard)) {
            $input['is_default'] = false;
        }

        $standard = Standard::create($input);
        if (isset($request->dublicateStandard) && $request->dublicateStandard != 0) {
            $copycontrols = StandardControl::where('standard_id', $request->dublicateStandard)->get();
            if ($copycontrols) {
                foreach ($copycontrols as $control) {
                    $standardControl = new StandardControl();
                    $standardControl->name = $control->name;
                    $standardControl->standard_id = $standard->id;
                    $standardControl->slug = $control->slug;
                    $standardControl->primary_id = $control->primary_id;
                    $standardControl->sub_id = $control->sub_id;
                    $standardControl->id_separator = $control->id_separator;
                    $standardControl->description = $control->description;
                    $standardControl->save();
                }
            }
            Log::info('User has duplicated a new compliance template standard',[
                'duplicated_standard_id' => $request->dublicateStandard,
                'new_standard_id' => $standard->id
            ]);
            return redirect(route('compliance-template-view-controls', [$standard->id]))->with('success', 'Standard added successfully.');
        }
        Log::info('User has created a new compliance template standard',[
            'standard_id' => $standard->id
        ]);
        return redirect(route('compliance-template-create-controls', [$standard->id]))->with('success', 'Standard added successfully.');
    }

    public function edit(Request $request, Standard $standard)
    {
        /* checking if it's a default standard */
        if ($standard->is_default) {
            return redirect()->back()->with('error', "This is a default standard and therefore can't be edited.");
        }
        Log::info('User is attempting to update a compliance template standard',[
            'standard_id' => $standard->id
        ]);
        return view('compliance.templates.create', compact('standard'));
    }

    public function update(Request $request, Standard $standard)
    {
        /* checking if it's a default standard */
        if ($standard->is_default) {
            return redirect()->back()->with('error', "This is a default standard and therefore can't be updated.");
        }

        $this->validate($request, [
            'name' => ['required', 'max:190', 'unique:compliance_standards,name,' . $standard->id],
            'version' => 'required|max:190',
        ]);

        if ($standard->projects()->count() > 0) {
            return redirect()->back()->with('error', 'Standard is assigned to projects and therefore cannot be modified.');
        }



        $input = $request->toArray();
        $updated = $standard->fill($input)->save();

        Log::info('User has updated a compliance template standard',[
            'standard_id' => $standard->id
        ]);
        return redirect(route('compliance-template-view'))->with('success', 'Standard updated successfully.');
    }

    public function delete(Request $request, Standard $standard)
    {
        /* checking if it's a default standard */
        if ($standard->is_default) {
            return redirect()->back()->with('error', "This is a default standard and therefore can't be deleted.");
        }

        $standard_id = $standard->id;
        $standard->delete();
        Log::info('User has deleted a compliance template standard',[
            'standard_id' => $standard_id
        ]);
        return redirect(route('compliance-template-view'))->with('success', 'Standard deleted successfully.');
    }

    public function viewControls(Request $request, Standard $standard)
    {
        return 'view control';
    }

    public function getJsonData(Request $request)
    {
        $start = $request->start;
        $length = $request->length;
        $draw = $request->draw;
        $keyword = $request->search['value'];
        $count = Standard::all();
        $standards = Standard::select(['id', 'name', 'version', 'is_default', 'created_at'])
                ->when($request->search['value'] != null, function ($query) use ($keyword) {
                    return $query->where('name', 'LIKE', '%'.$keyword.'%');
                })
                ->offset($start)
                ->take($length)
                ->get();

        $render = [];
        foreach ($standards as $key => $standard) {
            //get current page
            $currentpage = $start / $length + 1;

            //get the serial number
            $serialName = ($currentpage - 1) * $length + $key + 1;

            $actions = "<div class='btn-group'>";

            /* SHOWING ADD CONTROL(S) OPTIONS FOR ONLY NON-DEFAULT STANDARDS */

            $actions .= "<a href='" . route('compliance-template-view-controls', [$standard->id]) . "' title='View' class='btn btn-secondary btn-xs waves-effect waves-light'
                                data-toggle='tooltip' data-original-title='View'><i class='fe-eye'></i></a>";
            $actions .= "<a href='" . route('compliance-template-dublicate', [$standard->id]) . "' title='Dublicate Standard' class='btn btn-primary btn-xs waves-effect waves-light'
                                                data-animation='blur'><i class='far fa-plus-square'></i></a>";

            if (!$standard->is_default) {
                $actions .= "<a href='" . route('compliance-template-create-controls', [$standard->id]) . "' title='Add control(s)' class='btn btn-primary btn-xs waves-effect waves-light'
                                                data-animation='blur'><i class='fa fa-plus'></i></a>";
            }

            /* SHOWING EDIT AND DELETE OPTIONS FOR ONLY NON-DEFAULT STANDARDS */
            if (!$standard->is_default) {
                $actions .= "<a href='" . route('compliance-template-edit', [$standard->id]) .
                    "' title='Edit Information' class='btn btn-info btn-xs waves-effect waves-light' data-toggle='tooltip' data-original-title='Edit'><i class='fe-edit'></i></a>
                            <a href='" . route('compliance-template-delete', [$standard->id]) . "' title='Delete' class='btn btn-danger btn-xs waves-effect waves-light delete-standard-btn'
                            data-animation='blur' data-plugin='custommodal' data-overlayColor='#38414a'><i class='fe-trash-2'></i></a>";
            }

            $actions .= '</div>';
            $controls = "<span class='badge bg-info'>" . count($standard->controls) . ' Controls</span>';
            $render[] = [
                $serialName,
                $standard->name,
                $standard->version,
                $controls,
                date('j M Y, H:i:s', strtotime($standard->created_at)),
                $actions,
            ];
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => count($count),
            'recordsFiltered' => count($count),
            'data' => $render,
        ];
        echo json_encode($response);
    }

    public function getStandardList(Request $request)
    {
        $standards = Standard::get();

        return response()->json([
            'success' => true,
            'data' => $standards
        ]);
    }
}
