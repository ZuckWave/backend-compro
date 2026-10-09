<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\WorkOrderItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SparePartUsageReportController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = WorkOrderItem::query()->with('sparePart', 'workOrder.vehicle')->whereHas('workOrder', fn ($orders) => $orders->where('status', '!=', 'cancelled'));
        $query = $this->filtered($query, $request, [], ['spare_part_id'], 'created_at');
        $summary = [
            'quantity' => (int) (clone $query)->sum('quantity'),
            'value' => (string) BigDecimal::of((string) (clone $query)->selectRaw('COALESCE(SUM(quantity * unit_price), 0) AS value')->value('value'))->toScale(2, RoundingMode::HalfUp),
        ];

        return $this->listing($query, $request)->additional(['summary' => $summary]);
    }
}
