<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\MinimumStockRequest;
use App\Http\Resources\BusinessResource;
use App\Models\SparePart;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MinimumStockController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(SparePart::query()->with('category')->whereColumn('stock', '<=', 'min_stock'), $request, ['name', 'code'], ['category_id']);
    }

    public function update(MinimumStockRequest $request, SparePart $sparePart): BusinessResource
    {
        DB::transaction(fn () => $sparePart->update($request->validated()));

        return new BusinessResource($sparePart->refresh()->load('category'));
    }
}
