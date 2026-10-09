<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Requests\PurchaseOrderStatusRequest;
use App\Http\Resources\BusinessResource;
use App\Models\PurchaseOrder;
use App\Services\ProcurementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PurchaseOrderController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(PurchaseOrder::query()->with('supplier', 'items.sparePart'), $request, ['number'], ['status', 'supplier_id'], 'order_date');
    }

    public function store(PurchaseOrderRequest $request, ProcurementService $procurement): BusinessResource
    {
        return new BusinessResource($procurement->createOrder($request->validated(), $request->user()->id));
    }

    public function show(PurchaseOrder $purchaseOrder): BusinessResource
    {
        return new BusinessResource($purchaseOrder->load('supplier', 'items.sparePart', 'user'));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ProcurementService $procurement): BusinessResource
    {
        return new BusinessResource($procurement->updateOrder($purchaseOrder, $request->validated()));
    }

    public function status(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder, ProcurementService $procurement): BusinessResource
    {
        return new BusinessResource($procurement->transition($purchaseOrder, $request->validated('status')));
    }

    public function destroy(PurchaseOrder $purchaseOrder, ProcurementService $procurement): Response
    {
        $procurement->deleteOrder($purchaseOrder);

        return response()->noContent();
    }
}
