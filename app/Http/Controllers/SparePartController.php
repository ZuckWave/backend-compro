<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\SparePartRequest;
use App\Http\Resources\BusinessResource;
use App\Models\SparePart;
use App\Services\InventoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SparePartController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = SparePart::query()->with('category');
        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $this->listing($query, $request, ['code', 'name'], ['category_id']);
    }

    public function store(SparePartRequest $request, InventoryService $inventory): BusinessResource
    {
        return new BusinessResource($inventory->createPart($request->validated(), $request->user()->id));
    }

    public function show(SparePart $sparePart): BusinessResource
    {
        return new BusinessResource($sparePart->load('category'));
    }

    public function update(SparePartRequest $request, SparePart $sparePart): BusinessResource
    {
        DB::transaction(fn () => $sparePart->update($request->validated()));

        return new BusinessResource($sparePart->refresh()->load('category'));
    }

    public function destroy(SparePart $sparePart): Response
    {
        DB::transaction(fn () => $sparePart->delete());

        return response()->noContent();
    }
}
