<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\VehicleRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Vehicle;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(Vehicle::query(), $request, ['owner_name', 'plate_number']);
    }

    public function store(VehicleRequest $request): BusinessResource
    {
        return new BusinessResource(DB::transaction(fn () => Vehicle::create($request->validated())));
    }

    public function show(Vehicle $vehicle): BusinessResource
    {
        return new BusinessResource($vehicle);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): BusinessResource
    {
        DB::transaction(fn () => $vehicle->update($request->validated()));

        return new BusinessResource($vehicle->refresh());
    }

    public function destroy(Vehicle $vehicle): Response
    {
        DB::transaction(fn () => $vehicle->delete());

        return response()->noContent();
    }
}
