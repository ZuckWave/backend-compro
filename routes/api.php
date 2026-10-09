<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\MinimumStockController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReportController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentStatusController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\SparePartUsageReportController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderItemController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

Route::middleware(['auth:sanctum', 'role:owner,mekanik'])->group(function (): void {
    Route::get('user', [AuthController::class, 'profile'])->name('profile');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::apiResource('spare-parts', SparePartController::class)->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('vehicles', VehicleController::class);
    Route::apiResource('work-orders', WorkOrderController::class)->except(['destroy']);
    Route::apiResource('work-orders.items', WorkOrderItemController::class)->scoped();
    Route::get('spare-part-usage', [WorkOrderItemController::class, 'usage'])->name('spare-part-usage.index');

    Route::middleware('role:owner')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::apiResource('spare-parts', SparePartController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::get('stock-in', [StockInController::class, 'index'])->name('stock-in.index');
        Route::get('stock-out', [StockOutController::class, 'index'])->name('stock-out.index');
        Route::apiResource('stock-opnames', StockOpnameController::class)->only(['index', 'store', 'show']);

        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::patch('purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'status'])->name('purchase-orders.status');
        Route::apiResource('goods-receipts', GoodsReceiptController::class)->only(['index', 'store', 'show']);

        Route::apiResource('shipments', ShipmentController::class)->except(['destroy']);
        Route::get('shipments/{shipment}/statuses', [ShipmentStatusController::class, 'index'])->name('shipments.statuses.index');
        Route::post('shipments/{shipment}/statuses', [ShipmentStatusController::class, 'store'])->name('shipments.statuses.store');

        Route::get('reports/stock', [StockReportController::class, 'index'])->name('reports.stock');
        Route::get('reports/purchases', [PurchaseReportController::class, 'index'])->name('reports.purchases');
        Route::get('reports/spare-part-usage', [SparePartUsageReportController::class, 'index'])->name('reports.spare-part-usage');
        Route::get('reports/suppliers', [SupplierReportController::class, 'index'])->name('reports.suppliers');

        Route::apiResource('users', UserController::class);
        Route::get('minimum-stock', [MinimumStockController::class, 'index'])->name('minimum-stock.index');
        Route::patch('minimum-stock/{spare_part}', [MinimumStockController::class, 'update'])->name('minimum-stock.update');
        Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
    });
});
