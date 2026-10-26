<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\WorkOrderRequest;
use App\Http\Resources\BusinessResource;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkOrderController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = WorkOrder::query()->with('vehicle', 'mechanic', 'items.sparePart');
        if ($request->user()->role === 'mekanik') {
            $query->where('mechanic_id', $request->user()->id);
        }

        return $this->listing($query, $request, ['number', 'complaint'], ['status'], 'created_at');
    }

    public function store(WorkOrderRequest $request, WorkOrderService $workOrders): BusinessResource
    {
        return new BusinessResource($workOrders->create($request->validated()));
    }

    public function show(Request $request, WorkOrder $workOrder): BusinessResource
    {
        $this->authorizeAccess($request, $workOrder);

        return new BusinessResource($workOrder->load('vehicle', 'mechanic', 'items.sparePart'));
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder, WorkOrderService $workOrders): BusinessResource
    {
        $this->authorizeAccess($request, $workOrder);

        return new BusinessResource($workOrders->update($workOrder, $request->validated(), $request->user()->id));
    }

    private function authorizeAccess(Request $request, WorkOrder $workOrder): void
    {
        abort_unless(
            $request->user()->role === 'owner' || $workOrder->mechanic_id === $request->user()->id,
            404,
        );
    }
}
