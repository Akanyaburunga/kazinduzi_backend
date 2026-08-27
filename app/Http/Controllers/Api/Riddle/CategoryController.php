<?php

namespace App\Http\Controllers\Api\Riddle;

use App\Http\Controllers\Controller;
use App\Http\Requests\Riddle\StoreCategoryRequest;
use App\Http\Requests\Riddle\UpdateCategoryRequest;
use App\Models\RiddleCategory;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use IsCurator;

    /**
     * List all riddle categories (accessible to any verified user).
     */
    public function index()
    {
        $categories = RiddleCategory::withCount('riddles')->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * Create a category.
     */
    public function store(StoreCategoryRequest $request)
    {
        $this->authorizeCurator();

        $category = RiddleCategory::create([
            'name' => $request->name,
            'slug' => $request->slug ?? Str::slug($request->name),
            'description' => $request->description,
        ]);

        return response()->json(['success' => true, 'data' => $category], 201);
    }

    /**
     * Update a category.
     */
    public function update(UpdateCategoryRequest $request, RiddleCategory $category)
    {
        $this->authorizeCurator();

        $data = $request->only(['name', 'description']);
        if ($request->filled('slug')) {
            $data['slug'] = $request->slug;
        } elseif ($request->filled('name') && !$request->has('slug')) {
            $data['slug'] = Str::slug($request->name);
        }

        $category->update($data);

        return response()->json(['success' => true, 'data' => $category]);
    }

    /**
     * Delete a category (riddles keep existing with null category).
     */
    public function destroy(RiddleCategory $category)
    {
        $this->authorizeCurator();

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }
}
