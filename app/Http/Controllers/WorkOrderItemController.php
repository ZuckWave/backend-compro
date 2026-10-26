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
        $this->authorizeAccess($request, $workOrder);

        return $this->listing($workOrder->items()->with('sparePart')->getQuery(), $request, [], ['spare_part_id']);
    }

    public function usage(ListRequest $request): AnonymousResourceCollection
    {
        $query = WorkOrderItem::query()->with('sparePart', 'workOrder.vehicle')->whereHas('workOrder', function ($orders) use ($request): void {
            $orders->where('status', '!=', 'cancelled');
            if ($request->user()->role === 'mekanik') {
                $orders->where('mechanic_id', $request->user()->id);
            }
        });

        return $this->listing($query, $request, [], ['spare_part_id'], 'created_at');
    }

    public function store(WorkOrderItemRequest $request, WorkOrder $workOrder, WorkOrderService $workOrders): BusinessResource
    {
        $this->authorizeAccess($request, $workOrder);

        return new BusinessResource($workOrders->addItem($workOrder, $request->validated(), $request->user()->id));
    }

    public function show(Request $request, WorkOrder $workOrder, WorkOrderItem $item): BusinessResource
    {
        $this->authorizeAccess($request, $workOrder);

        return new BusinessResource($item->load('sparePart'));
    }

    public function update(WorkOrderItemRequest $request, WorkOrder $workOrder, WorkOrderItem $item, WorkOrderService $workOrders): BusinessResource
    {
        $this->authorizeAccess($request, $workOrder);

        return new BusinessResource($workOrders->updateItem($workOrder, $item, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, WorkOrder $workOrder, WorkOrderItem $item, WorkOrderService $workOrders): Response
    {
        $this->authorizeAccess($request, $workOrder);
        $workOrders->deleteItem($workOrder, $item, $request->user()->id);

        return response()->noContent();
    }

    private function authorizeAccess(Request $request, WorkOrder $workOrder): void
    {
        abort_unless(
            $request->user()->role === 'owner' || $workOrder->mechanic_id === $request->user()->id,
            404,
        );
    }
}
