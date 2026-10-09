<?php

namespace App\Services;

use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\StockOpname;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryService
{
    public function change(SparePart $sparePart, int $delta, string $type, ?Model $reference, ?int $userId, ?string $notes = null): ?StockMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Perubahan stok harus berada dalam transaksi.');
        }

        $part = SparePart::query()->lockForUpdate()->findOrFail($sparePart->id);
        $before = $part->stock;
        $after = $before + $delta;

        if ($after < 0) {
            throw ValidationException::withMessages(['quantity' => "Stok {$part->name} tidak mencukupi. Stok tersedia: {$before}."]);
        }
        if ($after > 1000000000) {
            throw ValidationException::withMessages(['quantity' => 'Stok melebihi batas maksimum.']);
        }
        if ($delta === 0) {
            return null;
        }

        $part->update(['stock' => $after]);

        return StockMovement::create([
            'spare_part_id' => $part->id,
            'type' => $type,
            'quantity' => $type === 'opname' ? $delta : abs($delta),
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    public function createPart(array $data, int $userId): SparePart
    {
        return DB::transaction(function () use ($data, $userId): SparePart {
            $part = SparePart::create(Arr::except($data, ['opening_stock']));
            $this->change($part, (int) ($data['opening_stock'] ?? 0), 'in', $part, $userId, 'Stok awal');

            return $part->refresh()->load('category');
        }, 3);
    }

    public function opname(array $data, int $userId): StockOpname
    {
        return DB::transaction(function () use ($data, $userId): StockOpname {
            $opname = StockOpname::create([
                ...Arr::except($data, ['items']),
                'number' => $data['number'] ?? 'SO-'.Str::ulid(),
                'status' => 'completed',
                'user_id' => $userId,
            ]);

            foreach (collect($data['items'])->sortBy('spare_part_id') as $row) {
                $part = SparePart::query()->lockForUpdate()->findOrFail($row['spare_part_id']);
                if ($part->stock !== (int) $row['system_stock']) {
                    throw ValidationException::withMessages(['items' => "Stok {$part->name} berubah sejak dihitung. Muat ulang dan hitung kembali."]);
                }

                $difference = (int) $row['physical_stock'] - $part->stock;
                $item = $opname->items()->create([
                    'spare_part_id' => $part->id,
                    'system_stock' => $part->stock,
                    'physical_stock' => $row['physical_stock'],
                    'difference' => $difference,
                    'notes' => $row['notes'] ?? null,
                ]);
                $this->change($part, $difference, 'opname', $item, $userId, $row['notes'] ?? $data['notes'] ?? 'Stock opname');
            }

            return $opname->load('items.sparePart', 'user');
        }, 3);
    }
}
