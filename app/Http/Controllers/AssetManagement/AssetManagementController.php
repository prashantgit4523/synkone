<?php

namespace App\Http\Controllers\AssetManagement;

use App\Exports\AssetsExport;
use App\Http\Controllers\Controller;
use App\Models\AssetManagement\Asset;
use App\Models\Integration\IntegrationCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetManagementController extends Controller
{
    public function index()
    {
        $category = IntegrationCategory::firstWhere('name', 'Asset Management and Helpdesk');
        $should_connect = $category->integrations()->where('connected', true)->doesntExist();

        return Inertia::render('asset-management/AssetManagement', compact('category', 'should_connect'));
    }

    public function getJsonData(Request $request)
    {
        $per_page = $request->input('per_page') ?? 10;

        $assets = Asset::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query
                    ->where(function ($query) use ($request) {
                        $query
                            ->where('name', 'LIKE', '%' . $request->search . '%')
                            ->orWhere('description', 'LIKE', '%' . $request->search . '%')
                            ->orWhere('owner', 'LIKE', '%' . $request->search . '%')
                            ->orWhere('type', 'LIKE', '%' . $request->search . '%')
                            ->orWhere('classification', 'LIKE', '%' . $request->search . '%');
                    });
            })
            ->when($request->filled('sort_by'), function ($query) use ($request) {
                $direction = $request->sort_type === 'asc' ? 'ASC' : 'DESC';
                $query->orderBy($request->sort_by, $direction);
            })
            ->paginate($per_page);

        return response()->json([
            'data' => $assets
        ]);
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new AssetsExport, 'export.xlsx');
    }
}
