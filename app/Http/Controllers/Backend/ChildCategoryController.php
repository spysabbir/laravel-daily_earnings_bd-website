<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ChildCategory;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class ChildCategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ChildCategory::select('child_categories.*');

            $query->orderBy('created_at', 'desc');

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $child_categories = $query->get();

            return DataTables::of($child_categories)
                ->addIndexColumn()
                ->addColumn('category_name', function ($row) {
                    return $row->category->name ?? 'N/A';
                })
                ->editColumn('sub_category_name', function ($row) {
                    return $row->subCategory->name ?? 'N/A';
                })
                ->editColumn('status', function ($row) {
                    if ($row->status == 'Active') {
                        $status = '
                        <span class="badge bg-success">' . $row->status . '</span>
                        <button type="button" data-id="' . $row->id . '" class="btn btn-warning btn-xs statusBtn">Deactive</button>
                        ';
                    } else {
                        $status = '
                        <span class="badge text-white bg-warning">' . $row->status . '</span>
                        <button type="button" data-id="' . $row->id . '" class="btn btn-success btn-xs statusBtn">Active</button>
                        ';
                    }
                    return $status;
                })
                ->addColumn('action', function ($row) {
                    $btn = '
                        <button type="button" data-id="' . $row->id . '" class="btn btn-primary btn-xs editBtn" data-bs-toggle="modal" data-bs-target=".editModal">Edit</button>
                        <button type="button" data-id="' . $row->id . '" class="btn btn-danger btn-xs deleteBtn">Delete</button>
                        ';
                    return $btn;
                })
                ->rawColumns(['category_name', 'sub_category_name', 'status', 'action'])
                ->make(true);
        }

        $categories = Category::where('status', 'Active')->get();
        $sub_categories = SubCategory::where('status', 'Active')->get();

        return view('backend.child_category.index', compact('categories', 'sub_categories'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'name' => 'required|string|max:255|unique:child_categories,name',
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => 400,
                'error'=> $validator->errors()->toArray()
            ]);
        }else{
            ChildCategory::create([
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'created_by' => auth()->user()->id,
            ]);

            return response()->json([
                'status' => 200,
            ]);
        }
    }

    public function edit(string $id)
    {
        $child_category = ChildCategory::where('id', $id)->first();
        return response()->json($child_category);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'name' => 'required|string|max:255|unique:child_categories,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'error' => $validator->errors()->toArray()
            ]);
        } else {
            ChildCategory::where('id', $id)->update([
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'updated_by' => auth()->user()->id,
            ]);

            return response()->json([
                'status' => 200,
            ]);
        }
    }

    public function destroy(string $id)
    {
        $child_category = ChildCategory::findOrFail($id);
        $child_category->delete();
    }

    public function trash(Request $request)
    {
        if ($request->ajax()) {
            $query = ChildCategory::onlyTrashed();

            $trash_child_categories = $query->orderBy('deleted_at', 'desc')->get();

            return DataTables::of($trash_child_categories)
                ->addIndexColumn()
                ->addColumn('category_name', function ($row) {
                    return $row->category->name ?? 'N/A';
                })
                ->editColumn('sub_category_name', function ($row) {
                    return $row->subCategory->name ?? 'N/A';
                })
                ->addColumn('action', function ($row) {
                    $btn = '
                        <button type="button" data-id="'.$row->id.'" class="btn bg-success btn-xs restoreBtn">Restore</button>
                        <button type="button" data-id="'.$row->id.'" class="btn bg-danger btn-xs forceDeleteBtn">Delete</button>
                    ';
                    return $btn;
                })
                ->rawColumns(['category_name', 'sub_category_name', 'action'])
                ->make(true);
        }

        return view('backend.child_category.index');
    }

    public function restore(string $id)
    {
        ChildCategory::onlyTrashed()->where('id', $id)->update([
            'deleted_by' => NULL
        ]);

        ChildCategory::onlyTrashed()->where('id', $id)->restore();
    }

    public function delete(string $id)
    {
        $child_category = ChildCategory::onlyTrashed()->where('id', $id)->first();
        $child_category->forceDelete();
    }

    public function status(string $id)
    {
        $child_category = ChildCategory::findOrFail($id);

        if ($child_category->status == "Active") {
            $child_category->status = "Inactive";
        } else {
            $child_category->status = "Active";
        }

        $child_category->updated_by = auth()->user()->id;
        $child_category->save();
    }
}