<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\SparePart;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockReportController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = $this->filtered(SparePart::query()->with('category'), $request, ['code', 'name'], ['category_id']);
        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }
        $summary = [
            'part_count' => (clone $query)->count(),
            'stock_quantity' => (int) (clone $query)->sum('stock'),
            'low_stock_count' => (clone $query)->whereColumn('stock', '<=', 'min_stock')->count(),
            'purchase_value' => (string) BigDecimal::of((string) (clone $query)->selectRaw('COALESCE(SUM(stock * purchase_price), 0) AS value')->value('value'))->toScale(2, RoundingMode::HalfUp),
            'selling_value' => (string) BigDecimal::of((string) (clone $query)->selectRaw('COALESCE(SUM(stock * selling_price), 0) AS value')->value('value'))->toScale(2, RoundingMode::HalfUp),
        ];

        return $this->listing($query, $request)->additional(['summary' => $summary]);
    }
}
