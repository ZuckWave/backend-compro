<?php

namespace App\Services;

use App\Models\SparePart;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function __construct(private InventoryService $inventory) {}

    private function editable(WorkOrder $order): void
    {
        if (! in_array($order->status, ['open', 'in_progress'], true)) {
            throw ValidationException::withMessages(['status' => 'Work order selesai atau dibatalkan tidak dapat diubah.']);
        }
    }

    private function verifyMechanic(int $mechanicId): void
    {
        $mechanic = User::query()->lockForUpdate()->findOrFail($mechanicId);
        if ($mechanic->role !== 'mekanik') {
            throw ValidationException::withMessages(['mechanic_id' => 'User yang ditugaskan harus memiliki role mekanik.']);
        }
    }

    public function create(array $data): WorkOrder
    {
        return DB::transaction(function () use ($data): WorkOrder {
            $this->verifyMechanic((int) $data['mechanic_id']);
            $order = WorkOrder::create([...$data, 'number' => $data['number'] ?? 'WO-'.Str::ulid()]);

            return $order->load('vehicle', 'mechanic', 'items.sparePart');
        }, 3);
    }

    public function update(WorkOrder $order, array $data, int $userId): WorkOrder
    {
        return DB::transaction(function () use ($order, $data, $userId): WorkOrder {
            $order = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->editable($order);
            if (isset($data['mechanic_id'])) {
                $this->verifyMechanic((int) $data['mechanic_id']);
            }
            if ($order->status === 'in_progress' && ($data['status'] ?? null) === 'open') {
                throw ValidationException::withMessages(['status' => 'Work order yang berjalan tidak dapat kembali ke open.']);
            }
            if (($data['status'] ?? null) === 'cancelled') {
                foreach ($order->items()->orderBy('spare_part_id')->get() as $item) {
                    $part = SparePart::query()->findOrFail($item->spare_part_id);
                    $this->inventory->change($part, $item->quantity, 'in', $item, $userId, 'Pembatalan '.$order->number);
                }
            }
            if (($data['status'] ?? null) === 'completed') {
                $data['completed_at'] = now();
            }
            $order->update($data);

            return $order->load('vehicle', 'mechanic', 'items.sparePart');
        }, 3);
    }

    public function addItem(WorkOrder $order, array $data, int $userId): WorkOrderItem
    {
        return DB::transaction(function () use ($order, $data, $userId): WorkOrderItem {
            $order = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->editable($order);
            if ($order->items()->where('spare_part_id', $data['spare_part_id'])->exists()) {
                throw ValidationException::withMessages(['spare_part_id' => 'Spare part sudah ada. Ubah quantity item yang ada.']);
            }
            $part = SparePart::query()->lockForUpdate()->findOrFail($data['spare_part_id']);
            $item = $order->items()->create([...$data, 'unit_price' => $data['unit_price'] ?? $part->selling_price]);
            $this->inventory->change($part, -(int) $data['quantity'], 'out', $item, $userId, 'Penggunaan '.$order->number);

            return $item->load('sparePart');
        }, 3);
    }

    public function updateItem(WorkOrder $order, WorkOrderItem $item, array $data, int $userId): WorkOrderItem
    {
        return DB::transaction(function () use ($order, $item, $data, $userId): WorkOrderItem {
            $order = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->editable($order);
            $item = $order->items()->lockForUpdate()->findOrFail($item->id);
            $part = SparePart::query()->findOrFail($item->spare_part_id);
            $delta = $item->quantity - (int) $data['quantity'];
            $this->inventory->change($part, $delta, $delta > 0 ? 'in' : 'out', $item, $userId, 'Koreksi penggunaan '.$order->number);
            $item->update($data);

            return $item->load('sparePart');
        }, 3);
    }

    public function deleteItem(WorkOrder $order, WorkOrderItem $item, int $userId): void
    {
        DB::transaction(function () use ($order, $item, $userId): void {
            $order = WorkOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->editable($order);
            $item = $order->items()->lockForUpdate()->findOrFail($item->id);
            $part = SparePart::query()->findOrFail($item->spare_part_id);
            $this->inventory->change($part, $item->quantity, 'in', $order, $userId, 'Penghapusan item '.$item->id);
            $item->delete();
        }, 3);
    }
}
