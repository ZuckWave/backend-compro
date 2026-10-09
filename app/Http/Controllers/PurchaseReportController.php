<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseReportController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $orders = $this->filtered(PurchaseOrder::query()->with('supplier', 'items.sparePart'), $request, ['number'], ['supplier_id', 'status'], 'order_date');
        $activeIds = (clone $orders)->whereIn('status', ['ordered', 'partial', 'received'])->select('id');
        $ordered = PurchaseOrderItem::query()->whereIn('purchase_order_id', $activeIds);
        $received = GoodsReceiptItem::query()->whereHas('goodsReceipt', fn ($query) => $query->whereIn('purchase_order_id', (clone $orders)->select('id')));
        $summary = [
            'order_count' => (clone $orders)->count(),
            'ordered_quantity' => (int) (clone $ordered)->sum('quantity'),
            'ordered_value' => (string) BigDecimal::of((string) (clone $ordered)->selectRaw('COALESCE(SUM(quantity * unit_price), 0) AS value')->value('value'))->toScale(2, RoundingMode::HalfUp),
            'received_quantity' => (int) (clone $received)->sum('quantity'),
            'received_value' => (string) BigDecimal::of((string) (clone $received)->selectRaw('COALESCE(SUM(quantity * unit_price), 0) AS value')->value('value'))->toScale(2, RoundingMode::HalfUp),
        ];

        return $this->listing($orders, $request)->additional(['summary' => $summary]);
    }
}
