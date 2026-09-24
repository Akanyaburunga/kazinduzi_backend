<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRiddleCategoryRequest;
use App\Http\Requests\Admin\UpdateRiddleCategoryRequest;
use App\Models\RiddleCategory;
use Illuminate\Support\Str;

class RiddleCategoryController extends Controller
{
    public function index()
    {
        $categories = RiddleCategory::withCount('riddles')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function store(StoreRiddleCategoryRequest $request)
    {
        $category = RiddleCategory::create([
            'name' => $request->name,
            'slug' => $request->slug ?? Str::slug($request->name),
            'description' => $request->description,
        ]);

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function update(UpdateRiddleCategoryRequest $request, RiddleCategory $category)
    {
        $data = $request->only(['name', 'description']);
        if ($request->filled('slug')) {
            $data['slug'] = Str::slug($request->slug);
        } elseif ($request->filled('name')) {
            $data['slug'] = Str::slug($request->name);
        }

        $category->update($data);

        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy(RiddleCategory $category)
    {
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }
}
