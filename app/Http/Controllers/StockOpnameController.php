<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\StockOpnameRequest;
use App\Http\Resources\BusinessResource;
use App\Models\StockOpname;
use App\Services\InventoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockOpnameController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(StockOpname::query()->with('items.sparePart', 'user'), $request, ['number'], [], 'opname_date');
    }

    public function store(StockOpnameRequest $request, InventoryService $inventory): BusinessResource
    {
        return new BusinessResource($inventory->opname($request->validated(), $request->user()->id));
    }

    public function show(StockOpname $stockOpname): BusinessResource
    {
        return new BusinessResource($stockOpname->load('items.sparePart', 'user'));
    }
}
