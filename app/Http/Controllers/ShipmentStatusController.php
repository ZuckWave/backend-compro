<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\ShipmentStatusRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShipmentStatusController extends Controller
{
    public function index(ListRequest $request, Shipment $shipment): AnonymousResourceCollection
    {
        return $this->listing($shipment->statusLogs()->with('user')->getQuery(), $request, [], [], 'created_at');
    }

    public function store(ShipmentStatusRequest $request, Shipment $shipment, ShipmentService $shipments): BusinessResource
    {
        return new BusinessResource($shipments->transition($shipment, $request->validated(), $request->user()->id));
    }
}
