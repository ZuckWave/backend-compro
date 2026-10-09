<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Supplier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(Supplier::query(), $request, ['name', 'phone']);
    }

    public function store(SupplierRequest $request): BusinessResource
    {
        return new BusinessResource(DB::transaction(fn () => Supplier::create($request->validated())));
    }

    public function show(Supplier $supplier): BusinessResource
    {
        return new BusinessResource($supplier);
    }

    public function update(SupplierRequest $request, Supplier $supplier): BusinessResource
    {
        DB::transaction(fn () => $supplier->update($request->validated()));

        return new BusinessResource($supplier->refresh());
    }

    public function destroy(Supplier $supplier): Response
    {
        DB::transaction(fn () => $supplier->delete());

        return response()->noContent();
    }
}
