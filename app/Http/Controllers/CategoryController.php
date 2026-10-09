<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(Category::query(), $request, ['name']);
    }

    public function store(CategoryRequest $request): BusinessResource
    {
        return new BusinessResource(DB::transaction(fn () => Category::create($request->validated())));
    }

    public function show(Category $category): BusinessResource
    {
        return new BusinessResource($category);
    }

    public function update(CategoryRequest $request, Category $category): BusinessResource
    {
        DB::transaction(fn () => $category->update($request->validated()));

        return new BusinessResource($category->refresh());
    }

    public function destroy(Category $category): Response
    {
        DB::transaction(fn () => $category->delete());

        return response()->noContent();
    }
}
