<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SparePart;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(private InventoryService $inventory) {}

    public function createOrder(array $data, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId): PurchaseOrder {
            $order = PurchaseOrder::create([
                ...Arr::except($data, ['items']),
                'number' => $data['number'] ?? 'PO-'.Str::ulid(),
                'user_id' => $userId,
                'status' => 'draft',
            ]);
            foreach ($data['items'] as $item) {
                $order->items()->create($item);
            }

            return $order->load('supplier', 'items.sparePart');
        }, 3);
    }

    public function updateOrder(PurchaseOrder $order, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->requireDraft($order);
            $order->update(Arr::except($data, ['items']));
            if (isset($data['items'])) {
                foreach ($order->items()->get() as $item) {
                    $item->delete();
                }
                foreach ($data['items'] as $item) {
                    $order->items()->create($item);
                }
            }

            return $order->load('supplier', 'items.sparePart');
        }, 3);
    }

    public function deleteOrder(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->requireDraft($order);
            foreach ($order->items()->get() as $item) {
                $item->delete();
            }
            $order->delete();
        }, 3);
    }

    private function requireDraft(PurchaseOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya purchase order draft yang dapat diubah atau dihapus.']);
        }
    }

    public function transition(PurchaseOrder $order, string $status): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $status): PurchaseOrder {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $allowed = [
                'draft' => ['ordered', 'cancelled'],
                'ordered' => ['cancelled'],
            ];
            if (! in_array($status, $allowed[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status purchase order tidak diizinkan.']);
            }
            $order->update(['status' => $status]);

            return $order->load('supplier', 'items.sparePart');
        }, 3);
    }

    public function receive(array $data, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $userId): GoodsReceipt {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($data['purchase_order_id']);
            if (! in_array($order->status, ['ordered', 'partial'], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Barang hanya dapat diterima dari PO ordered atau partial.']);
            }
            if ($data['received_date'] < $order->order_date->toDateString()) {
                throw ValidationException::withMessages(['received_date' => 'Tanggal penerimaan tidak boleh sebelum tanggal PO.']);
            }

            $receipt = GoodsReceipt::create([
                ...Arr::except($data, ['items']),
                'number' => $data['number'] ?? 'GR-'.Str::ulid(),
                'user_id' => $userId,
            ]);
            $orderItems = $order->items()->lockForUpdate()->get()->keyBy('id');
            $rows = collect($data['items'])->sortBy(fn (array $row): int => (int) ($orderItems->get($row['purchase_order_item_id'])?->spare_part_id ?? 0));

            foreach ($rows as $row) {
                $orderItem = $orderItems->get($row['purchase_order_item_id']);
                if (! $orderItem) {
                    throw ValidationException::withMessages(['items' => 'Item penerimaan tidak termasuk dalam purchase order ini.']);
                }
                $quantity = (int) $row['quantity'];
                if ($quantity > $orderItem->quantity - $orderItem->received_quantity) {
                    throw ValidationException::withMessages(['items' => 'Jumlah diterima melebihi sisa pesanan.']);
                }

                $item = $receipt->items()->create([
                    'purchase_order_item_id' => $orderItem->id,
                    'spare_part_id' => $orderItem->spare_part_id,
                    'quantity' => $quantity,
                    'unit_price' => $orderItem->unit_price,
                ]);
                $part = SparePart::query()->lockForUpdate()->findOrFail($orderItem->spare_part_id);
                $this->inventory->change($part, $quantity, 'in', $item, $userId, 'Penerimaan '.$receipt->number);
                $orderItem->update(['received_quantity' => $orderItem->received_quantity + $quantity]);
            }

            $incomplete = $order->items()->whereColumn('received_quantity', '<', 'quantity')->exists();
            $order->update(['status' => $incomplete ? 'partial' : 'received']);

            return $receipt->load('purchaseOrder.supplier', 'items.sparePart', 'user');
        }, 3);
    }
}
