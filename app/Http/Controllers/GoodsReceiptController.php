<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoodsReceiptRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\BusinessResource;
use App\Models\GoodsReceipt;
use App\Services\ProcurementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoodsReceiptController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(GoodsReceipt::query()->with('purchaseOrder.supplier', 'items.sparePart'), $request, ['number'], [], 'received_date');
    }

    public function store(GoodsReceiptRequest $request, ProcurementService $procurement): BusinessResource
    {
        return new BusinessResource($procurement->receive($request->validated(), $request->user()->id));
    }

    public function show(GoodsReceipt $goodsReceipt): BusinessResource
    {
        return new BusinessResource($goodsReceipt->load('purchaseOrder.supplier', 'items.sparePart', 'user'));
    }
}
