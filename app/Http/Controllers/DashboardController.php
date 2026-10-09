<?php

namespace App\Http\Controllers;

use App\Http\Resources\BusinessResource;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\SparePart;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'spare_part_count' => SparePart::query()->count(),
            'stock_quantity' => (int) SparePart::query()->sum('stock'),
            'low_stock_count' => SparePart::query()->whereColumn('stock', '<=', 'min_stock')->count(),
            'pending_purchase_orders' => PurchaseOrder::query()->whereIn('status', ['ordered', 'partial'])->count(),
            'active_work_orders' => WorkOrder::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'pending_shipments' => Shipment::query()->whereIn('status', ['pending', 'shipped'])->count(),
            'low_stock_parts' => BusinessResource::collection(SparePart::query()->with('category')->whereColumn('stock', '<=', 'min_stock')->orderBy('stock')->orderBy('id')->limit(10)->get()),
        ]]);
    }
}
