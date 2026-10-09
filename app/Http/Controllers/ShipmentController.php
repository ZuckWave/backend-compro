<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\ShipmentRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShipmentController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(Shipment::query()->with('statusLogs.user', 'workOrder'), $request, ['number', 'recipient_name', 'tracking_number'], ['status'], 'created_at');
    }

    public function store(ShipmentRequest $request, ShipmentService $shipments): BusinessResource
    {
        return new BusinessResource($shipments->create($request->validated(), $request->user()->id));
    }

    public function show(Shipment $shipment): BusinessResource
    {
        return new BusinessResource($shipment->load('statusLogs.user', 'workOrder'));
    }

    public function update(ShipmentRequest $request, Shipment $shipment, ShipmentService $shipments): BusinessResource
    {
        return new BusinessResource($shipments->update($shipment, $request->validated()));
    }
}
