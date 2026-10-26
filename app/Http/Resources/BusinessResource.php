<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
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
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = match ($this->resource::class) {
            Category::class => ['id', 'name', 'description', 'created_at', 'updated_at'],
            SparePart::class => ['id', 'code', 'name', 'category_id', 'supplier_id', 'unit', 'purchase_price', 'selling_price', 'stock', 'min_stock', 'created_at', 'updated_at'],
            StockMovement::class => ['id', 'spare_part_id', 'type', 'quantity', 'stock_before', 'stock_after', 'reference_type', 'reference_id', 'user_id', 'notes', 'created_at', 'updated_at'],
            StockOpname::class => ['id', 'number', 'opname_date', 'status', 'user_id', 'notes', 'created_at', 'updated_at'],
            StockOpnameItem::class => ['id', 'stock_opname_id', 'spare_part_id', 'system_stock', 'physical_stock', 'difference', 'notes', 'created_at', 'updated_at'],
            Supplier::class => ['id', 'name', 'contact_name', 'phone', 'email', 'address', 'created_at', 'updated_at'],
            PurchaseOrder::class => ['id', 'number', 'supplier_id', 'order_date', 'status', 'user_id', 'notes', 'created_at', 'updated_at'],
            PurchaseOrderItem::class => ['id', 'purchase_order_id', 'spare_part_id', 'quantity', 'received_quantity', 'unit_price', 'created_at', 'updated_at'],
            GoodsReceipt::class => ['id', 'number', 'purchase_order_id', 'received_date', 'user_id', 'notes', 'created_at', 'updated_at'],
            GoodsReceiptItem::class => ['id', 'goods_receipt_id', 'purchase_order_item_id', 'spare_part_id', 'quantity', 'unit_price', 'created_at', 'updated_at'],
            Vehicle::class => ['id', 'owner_name', 'phone', 'plate_number', 'brand', 'type', 'year', 'created_at', 'updated_at'],
            WorkOrder::class => ['id', 'number', 'vehicle_id', 'mechanic_id', 'complaint', 'status', 'service_fee', 'completed_at', 'notes', 'created_at', 'updated_at'],
            WorkOrderItem::class => ['id', 'work_order_id', 'spare_part_id', 'quantity', 'unit_price', 'created_at', 'updated_at'],
            Shipment::class => ['id', 'number', 'work_order_id', 'recipient_name', 'phone', 'address', 'courier', 'tracking_number', 'status', 'user_id', 'notes', 'created_at', 'updated_at'],
            ShipmentStatusLog::class => ['id', 'shipment_id', 'status', 'notes', 'user_id', 'created_at', 'updated_at'],
            AuditLog::class => ['id', 'user_id', 'action', 'table_name', 'record_id', 'old_values', 'new_values', 'ip_address', 'created_at', 'updated_at'],
            User::class => ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
        };

        $data = $this->resource->only($fields);
        foreach (['purchase_orders_count', 'pending_order_count'] as $count) {
            if (array_key_exists($count, $this->resource->getAttributes())) {
                $data[$count] = (int) $this->resource->getAttribute($count);
            }
        }
        $relations = ['category', 'supplier', 'vehicle', 'mechanic', 'user', 'sparePart', 'purchaseOrder', 'workOrder', 'items', 'statusLogs'];

        foreach ($relations as $relation) {
            if ($this->resource->relationLoaded($relation)) {
                $value = $this->resource->getRelation($relation);
                $key = Str::snake($relation);
                $data[$key] = $value instanceof Collection ? self::collection($value) : ($value ? new self($value) : null);
            }
        }

        if ($this->resource instanceof SparePart) {
            $data['is_low_stock'] = $this->stock <= $this->min_stock;
        }
        if ($this->resource instanceof PurchaseOrder || $this->resource instanceof GoodsReceipt || $this->resource instanceof WorkOrder) {
            if ($this->resource->relationLoaded('items')) {
                $total = BigDecimal::of(0);
                foreach ($this->resource->getRelation('items') as $item) {
                    $total = $total->plus(BigDecimal::of($item->unit_price)->multipliedBy($item->quantity));
                }
                $data['parts_total'] = (string) $total->toScale(2);
                $data['total'] = (string) $total->plus($this->resource instanceof WorkOrder ? $this->service_fee : 0)->toScale(2);
            }
        }

        return $data;
    }
}
