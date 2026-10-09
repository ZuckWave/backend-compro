<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\WorkOrderRequest;
use App\Http\Resources\BusinessResource;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkOrderController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(WorkOrder::query()->with('vehicle', 'mechanic', 'items.sparePart'), $request, ['number', 'complaint'], ['status'], 'created_at');
    }

    public function store(WorkOrderRequest $request, WorkOrderService $workOrders): BusinessResource
    {
        return new BusinessResource($workOrders->create($request->validated()));
    }

    public function show(WorkOrder $workOrder): BusinessResource
    {
        return new BusinessResource($workOrder->load('vehicle', 'mechanic', 'items.sparePart'));
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder, WorkOrderService $workOrders): BusinessResource
    {
        return new BusinessResource($workOrders->update($workOrder, $request->validated(), $request->user()->id));
    }
}
