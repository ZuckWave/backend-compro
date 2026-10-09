<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shipment;
use App\Models\ShipmentStatusLog;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Observers\AuditObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach ([
            Category::class,
            SparePart::class,
            StockMovement::class,
            StockOpname::class,
            StockOpnameItem::class,
            Supplier::class,
            PurchaseOrder::class,
            PurchaseOrderItem::class,
            GoodsReceipt::class,
            GoodsReceiptItem::class,
            Vehicle::class,
            WorkOrder::class,
            WorkOrderItem::class,
            Shipment::class,
            ShipmentStatusLog::class,
            User::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }

        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
    }
}
