<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function create(array $data, int $userId): Shipment
    {
        return DB::transaction(function () use ($data, $userId): Shipment {
            $shipment = Shipment::create([
                ...$data,
                'number' => $data['number'] ?? 'SH-'.Str::ulid(),
                'status' => 'pending',
                'user_id' => $userId,
            ]);
            $shipment->statusLogs()->create(['status' => 'pending', 'user_id' => $userId, 'notes' => $data['notes'] ?? null]);

            return $shipment->load('statusLogs.user', 'workOrder');
        }, 3);
    }

    public function update(Shipment $shipment, array $data): Shipment
    {
        return DB::transaction(function () use ($shipment, $data): Shipment {
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($shipment->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Hanya pengiriman pending yang dapat diubah.']);
            }
            $shipment->update($data);

            return $shipment->load('statusLogs.user', 'workOrder');
        }, 3);
    }

    public function transition(Shipment $shipment, array $data, int $userId): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $userId): Shipment {
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $allowed = ['pending' => ['shipped', 'cancelled'], 'shipped' => ['delivered']];
            if (! in_array($data['status'], $allowed[$shipment->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status pengiriman tidak diizinkan.']);
            }
            $shipment->update(['status' => $data['status']]);
            $shipment->statusLogs()->create([...$data, 'user_id' => $userId]);

            return $shipment->load('statusLogs.user', 'workOrder');
        }, 3);
    }
}
