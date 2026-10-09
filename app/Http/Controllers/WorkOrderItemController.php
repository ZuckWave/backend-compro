<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\WorkOrderItemRequest;
use App\Http\Resources\BusinessResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WorkOrderItemController extends Controller
{
    public function index(ListRequest $request, WorkOrder $workOrder): AnonymousResourceCollection
    {
        return $this->listing($workOrder->items()->with('sparePart')->getQuery(), $request, [], ['spare_part_id']);
    }

    public function usage(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(WorkOrderItem::query()->with('sparePart', 'workOrder.vehicle')->whereHas('workOrder', fn ($query) => $query->where('status', '!=', 'cancelled')), $request, [], ['spare_part_id'], 'created_at');
    }

    public function store(WorkOrderItemRequest $request, WorkOrder $workOrder, WorkOrderService $workOrders): BusinessResource
    {
        return new BusinessResource($workOrders->addItem($workOrder, $request->validated(), $request->user()->id));
    }

    public function show(WorkOrder $workOrder, WorkOrderItem $item): BusinessResource
    {
        return new BusinessResource($item->load('sparePart'));
    }

    public function update(WorkOrderItemRequest $request, WorkOrder $workOrder, WorkOrderItem $item, WorkOrderService $workOrders): BusinessResource
    {
        return new BusinessResource($workOrders->updateItem($workOrder, $item, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, WorkOrder $workOrder, WorkOrderItem $item, WorkOrderService $workOrders): Response
    {
        $workOrders->deleteItem($workOrder, $item, $request->user()->id);

        return response()->noContent();
    }
}
