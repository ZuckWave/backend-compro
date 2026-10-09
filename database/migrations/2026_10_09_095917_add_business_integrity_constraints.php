<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $checks = [
        'spare_parts' => "stock BETWEEN 0 AND 1000000000 AND min_stock BETWEEN 0 AND 1000000000 AND purchase_price >= 0 AND selling_price >= 0",
        'stock_movements' => "stock_before >= 0 AND stock_after >= 0 AND quantity <> 0 AND ((type = 'in' AND quantity > 0 AND stock_after - stock_before = quantity) OR (type = 'out' AND quantity > 0 AND stock_before - stock_after = quantity) OR (type = 'opname' AND stock_after - stock_before = quantity))",
        'stock_opname_items' => "system_stock >= 0 AND physical_stock >= 0 AND difference = physical_stock - system_stock",
        'purchase_order_items' => "quantity > 0 AND received_quantity >= 0 AND received_quantity <= quantity AND unit_price >= 0",
        'goods_receipt_items' => "quantity > 0 AND unit_price >= 0",
        'work_order_items' => "quantity > 0 AND unit_price >= 0",
        'work_orders' => "service_fee >= 0 AND ((status = 'completed') = (completed_at IS NOT NULL))",
        'vehicles' => "year IS NULL OR year >= 1900",
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->checks as $table => $check) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_business_check CHECK ({$check})");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys($this->checks) as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_business_check");
        }
    }
};
