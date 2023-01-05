<?php

namespace App\Http\Controllers\Compliance\Standard;

use App\Http\Controllers\Controller;
use App\Utils\RegularFunctions;
use App\Models\Compliance\Standard;
use App\Models\Compliance\StandardControl;
use App\Models\StandardCategory;
use Auth;
use Illuminate\Http\Request;

class ComplianceTemplateReactController extends Controller
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
        $categories = StandardCategory::with('standards')->orderBy('order_number')->get();

        foreach($categories as $category){
            foreach($category->standards as $standard){
                $standard['controls_count'] = StandardControl::select('id')->where('standard_id', $standard->id)->count();
            }
        }

        $standards = collect();
        foreach($categories as $standardCategory){
            $standards = $standards->merge(Standard::withCount('controls')->where('category_id',$standardCategory->id)->get());
        }

        $categories->prepend(collect([
            'id' => 0,
            'name' => 'All Categories',
            'standards' =>$standards
        ]));

        return inertia('compliance-template/StandardList', compact('categories'));
    }

    public function create()
    {
        $standard = new Standard();
        return inertia('compliance-template/StandardCreate', compact('standard'));
    }

    public function dublicate($id)
    {
        $dublicateStandard = $id;
        return inertia('compliance-template/StandardCreate', compact('dublicateStandard'));
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

        $standard = Standard::create(['category_id' => StandardCategory::CUSTOM_ID, 'logo' => 'Custom Standard.png'] + $input);

        if (isset($request->dublicateStandard) && $request->dublicateStandard != 0) {
            $copycontrols = StandardControl::where('standard_id', $request->dublicateStandard)->get();
            if ($copycontrols) {
                foreach ($copycontrols as $control) {
                    $standardControl = new StandardControl();
                    $standardControl->index = $control->index ? $control->index : null;
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

            return redirect(route('compliance-template-view-controls', [$standard->id]))->with('success', 'Standard added successfully.');
        }

        return redirect(route('compliance-template-create-controls', [$standard->id]))->with('success', 'Standard added successfully.');
    }

    public function edit(Request $request, Standard $standard)
    {
        /* checking if it's a default standard */
        if ($standard->is_default) {
            return redirect()->back()->with('error', "This is a default standard and therefore can't be edited.");
        }

        return inertia('compliance-template/StandardCreate', compact('standard'));

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

        return redirect(route('compliance-template-view'))->with('success', 'Standard updated successfully.');
    }

    public function delete(Request $request, Standard $standard)
    {
        /* checking if it's a default standard */
        if ($standard->is_default) {
            return redirect()->back()->with('error', "This is a default standard and therefore can't be deleted.");
        }
        /**
         * checking if it has associated projects.
         * !! if it has associated projects, it will not be deleted.
        
            if ($standard->projects()->exists()) {
                foreach($standard->projects as $project){
                    $project->delete();
                }
            } 
        */
        $standard->delete();

        return redirect(route('compliance-template-view'))->with('success', 'Standard deleted successfully.');
    }

    public function viewControls(Request $request, Standard $standard)
    {
        return 'view control';
    }

    public function getJsonData(Request $request)
    {
        $page = $request->page ?? 1;
        $size = $request->per_page ?? 10;
        $start = $request->start;
        $draw = $request->draw;
        $keyword = $request->search;
        $count = Standard::all();
        $standards = Standard::select(['id', 'name', 'version', 'is_default', 'created_at'])
            ->withCount('controls')
            ->when($request->search != null, function ($query) use ($keyword) {
                return $query->where('name', 'LIKE', '%' . $keyword . '%');
            })
            ->offset($start)
            ->take($size)
            ->paginate($size);

        foreach ($standards as $standard) {
            $standard['created_date'] = date('d M, Y', strtotime($standard->created_at));
        }

        return response()->json([
            'data' => $standards,
            'total' => $count,
        ]);
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
