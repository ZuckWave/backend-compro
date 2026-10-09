<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\Supplier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierReportController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = Supplier::query()->withCount([
            'purchaseOrders' => fn ($orders) => $this->filtered($orders, $request, [], [], 'order_date'),
            'purchaseOrders as pending_order_count' => fn ($orders) => $this->filtered($orders->whereIn('status', ['ordered', 'partial']), $request, [], [], 'order_date'),
        ]);

        return $this->listing($query, $request, ['name'], []);
    }
}
