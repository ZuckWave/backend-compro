<?php

namespace App\Http\Controllers;

use App\Http\Resources\BusinessResource;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $parts = SparePart::query()->get(['stock', 'min_stock', 'purchase_price']);
        $partCount = $parts->count();
        $belowMinimum = $parts->filter(fn (SparePart $part): bool => $part->stock <= $part->min_stock)->count();
        $nearMinimum = $parts->filter(fn (SparePart $part): bool => $part->stock > $part->min_stock && $part->stock <= $part->min_stock * 2)->count();
        $monthlyMovements = array_fill(1, 12, ['in' => 0, 'out' => 0]);

        StockMovement::query()
            ->whereYear('created_at', now()->year)
            ->get(['type', 'quantity', 'created_at'])
            ->each(function (StockMovement $movement) use (&$monthlyMovements): void {
                $month = Carbon::parse($movement->created_at)->month;
                if (isset($monthlyMovements[$month][$movement->type])) {
                    $monthlyMovements[$month][$movement->type] += (int) $movement->quantity;
                }
            });

        $topSelling = WorkOrderItem::query()
            ->selectRaw('spare_part_id, SUM(quantity) as quantity_sold')
            ->groupBy('spare_part_id')
            ->orderByDesc('quantity_sold')
            ->orderBy('spare_part_id')
            ->first();

        return response()->json(['data' => [
            'spare_part_count' => $partCount,
            'stock_quantity' => (int) $parts->sum('stock'),
            'inventory_value' => round($parts->sum(fn (SparePart $part): float => $part->stock * (float) $part->purchase_price), 2),
            'low_stock_count' => $belowMinimum,
            'pending_purchase_orders' => PurchaseOrder::query()->whereIn('status', ['ordered', 'partial'])->count(),
            'active_work_orders' => WorkOrder::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'today_work_orders' => WorkOrder::query()->whereDate('created_at', today())->count(),
            'pending_shipments' => Shipment::query()->whereIn('status', ['pending', 'shipped'])->count(),
            'stock_health' => [
                'safe' => max(0, $partCount - $belowMinimum - $nearMinimum),
                'near_minimum' => $nearMinimum,
                'below_minimum' => $belowMinimum,
            ],
            'monthly_movements' => collect($monthlyMovements)->map(fn (array $movement, int $month): array => [
                'month' => $month,
                'in' => $movement['in'],
                'out' => $movement['out'],
            ])->values(),
            'low_stock_parts' => BusinessResource::collection(
                SparePart::query()->with('category')->whereColumn('stock', '<=', 'min_stock')->orderBy('stock')->orderBy('id')->limit(10)->get(),
            ),
            'recent_movements' => BusinessResource::collection(
                StockMovement::query()->with('sparePart')->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
            ),
            'top_selling_part' => $topSelling ? [
                'quantity_sold' => (int) $topSelling->quantity_sold,
                'spare_part' => new BusinessResource(SparePart::query()->find($topSelling->spare_part_id)),
            ] : null,
            'recent_receipts' => BusinessResource::collection(
                GoodsReceipt::query()->with('items.sparePart')->orderByDesc('received_date')->orderByDesc('id')->limit(4)->get(),
            ),
        ]]);
    }
}
