<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::all();
    }

    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = Category::create($validated);

        return response()->json($category, 201);
    }
    public function show(Category $category)
    {
        return response()->json($category, 200);
    }
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validated = $request->validated();
        $category->update($validated);
        return response()->json($category, 200);
    }
    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(null, 204);
    }
}
