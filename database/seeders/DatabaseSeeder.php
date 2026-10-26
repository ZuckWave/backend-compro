<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\ShipmentStatusLog;
use App\Models\SparePart;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\WorkOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a realistic KEN KEN Motor demo workspace.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $owner = User::updateOrCreate(['email' => 'owner@kenkenmotor.test'], [
                'name' => 'Owner KEN KEN Motor',
                'password' => 'KenKenOwner123!',
                'role' => 'owner',
                'email_verified_at' => now(),
            ]);
            $mechanic = User::updateOrCreate(['email' => 'mekanik@kenkenmotor.test'], [
                'name' => 'Mekanik KEN KEN Motor',
                'password' => 'KenKenMekanik123!',
                'role' => 'mekanik',
                'email_verified_at' => now(),
            ]);

            $categories = collect([
                ['name' => 'CVT & Transmisi', 'description' => 'Roller, v-belt, kampas ganda, dan komponen CVT.'],
                ['name' => 'Oli & Pelumas', 'description' => 'Oli mesin, oli gardan, dan pelumas rantai.'],
                ['name' => 'Pengereman', 'description' => 'Kampas rem, minyak rem, dan komponen rem.'],
                ['name' => 'Kelistrikan', 'description' => 'Busi, aki, lampu, dan komponen pengapian.'],
                ['name' => 'Filter & Mesin', 'description' => 'Filter udara, filter oli, dan komponen servis mesin.'],
                ['name' => 'Ban & Roda', 'description' => 'Ban dalam, ban luar, dan komponen roda.'],
            ])->mapWithKeys(fn (array $row): array => [$row['name'] => Category::updateOrCreate(['name' => $row['name']], ['description' => $row['description']])]);

            $partRows = [
                ['code' => 'CVT-RLR-10G', 'name' => 'Roller CVT 10 gram (set 6 pcs)', 'category' => 'CVT & Transmisi', 'unit' => 'set', 'purchase_price' => 42000, 'selling_price' => 55000, 'stock' => 18, 'min_stock' => 5],
                ['code' => 'CVT-VBL-MIO', 'name' => 'V-belt Yamaha Mio M3', 'category' => 'CVT & Transmisi', 'unit' => 'pcs', 'purchase_price' => 85000, 'selling_price' => 110000, 'stock' => 8, 'min_stock' => 3],
                ['code' => 'CVT-KPG-BEAT', 'name' => 'Kampas ganda Honda BeAT', 'category' => 'CVT & Transmisi', 'unit' => 'set', 'purchase_price' => 68000, 'selling_price' => 90000, 'stock' => 6, 'min_stock' => 3],
                ['code' => 'OLI-MPX2-08', 'name' => 'Oli mesin MPX2 0.8 L', 'category' => 'Oli & Pelumas', 'unit' => 'botol', 'purchase_price' => 46000, 'selling_price' => 58000, 'stock' => 24, 'min_stock' => 8],
                ['code' => 'OLI-GRD-12', 'name' => 'Oli gardan matic 120 ml', 'category' => 'Oli & Pelumas', 'unit' => 'tube', 'purchase_price' => 12500, 'selling_price' => 18000, 'stock' => 16, 'min_stock' => 5],
                ['code' => 'BRK-KP-HB', 'name' => 'Kampas rem depan Honda BeAT', 'category' => 'Pengereman', 'unit' => 'set', 'purchase_price' => 33000, 'selling_price' => 45000, 'stock' => 2, 'min_stock' => 4],
                ['code' => 'ELE-BUS-CPR', 'name' => 'Busi NGK CPR9EA-9', 'category' => 'Kelistrikan', 'unit' => 'pcs', 'purchase_price' => 18500, 'selling_price' => 27000, 'stock' => 12, 'min_stock' => 4],
                ['code' => 'FLT-UDR-BEAT', 'name' => 'Filter udara Honda BeAT FI', 'category' => 'Filter & Mesin', 'unit' => 'pcs', 'purchase_price' => 27000, 'selling_price' => 38000, 'stock' => 5, 'min_stock' => 3],
                ['code' => 'TIR-DL-80-90', 'name' => 'Ban luar tubeless 80/90-14', 'category' => 'Ban & Roda', 'unit' => 'pcs', 'purchase_price' => 185000, 'selling_price' => 225000, 'stock' => 4, 'min_stock' => 2],
                ['code' => 'ELE-AKI-YTZ', 'name' => 'Aki motor YTZ5S', 'category' => 'Kelistrikan', 'unit' => 'pcs', 'purchase_price' => 205000, 'selling_price' => 255000, 'stock' => 3, 'min_stock' => 2],
            ];

            $parts = collect($partRows)->mapWithKeys(function (array $row) use ($categories): array {
                $part = SparePart::updateOrCreate(['code' => $row['code']], [
                    'name' => $row['name'],
                    'category_id' => $categories[$row['category']]->id,
                    'unit' => $row['unit'],
                    'purchase_price' => $row['purchase_price'],
                    'selling_price' => $row['selling_price'],
                    'min_stock' => $row['min_stock'],
                ]);

                if (! $part->stockMovements()->exists()) {
                    app(InventoryService::class)->change($part, $row['stock'], 'in', $part, null, 'Saldo awal demo KEN KEN Motor');
                }

                return [$row['code'] => $part->fresh()];
            });

            $supplierRows = [
                ['name' => 'Mitra Motor Parts', 'contact_name' => 'Budi Santoso', 'phone' => '0812-3456-7801', 'email' => 'mitra.parts@example.test', 'address' => 'Jl. Raya Bengkel No. 12, Jakarta'],
                ['name' => 'Sumber Rejeki Oli', 'contact_name' => 'Siti Rahma', 'phone' => '0813-4567-8902', 'email' => 'sumberrejeki.oli@example.test', 'address' => 'Jl. Industri Motor No. 8, Jakarta'],
                ['name' => 'Berkah Ban Nusantara', 'contact_name' => 'Agus Setiawan', 'phone' => '0815-6789-0123', 'email' => 'berkahban@example.test', 'address' => 'Jl. Lingkar Niaga No. 21, Bekasi'],
            ];
            $suppliers = collect($supplierRows)->mapWithKeys(fn (array $row): array => [$row['name'] => Supplier::firstOrCreate(['name' => $row['name']], $row)]);

            $vehicleRows = [
                ['owner_name' => 'Rina Amelia', 'phone' => '0812-1111-2201', 'plate_number' => 'B 3142 KKM', 'brand' => 'Honda', 'type' => 'BeAT FI', 'year' => 2022],
                ['owner_name' => 'Dimas Pratama', 'phone' => '0812-1111-2202', 'plate_number' => 'B 2781 KEN', 'brand' => 'Yamaha', 'type' => 'Mio M3', 'year' => 2021],
                ['owner_name' => 'Nadia Putri', 'phone' => '0812-1111-2203', 'plate_number' => 'B 4509 NKN', 'brand' => 'Honda', 'type' => 'Vario 125', 'year' => 2023],
                ['owner_name' => 'Fajar Ramadhan', 'phone' => '0812-1111-2204', 'plate_number' => 'B 1938 KKM', 'brand' => 'Yamaha', 'type' => 'NMAX 155', 'year' => 2020],
            ];
            $vehicles = collect($vehicleRows)->mapWithKeys(fn (array $row): array => [$row['plate_number'] => Vehicle::firstOrCreate(['plate_number' => $row['plate_number']], $row)]);

            $this->seedPurchaseOrder($suppliers['Mitra Motor Parts'], $owner, $parts['CVT-RLR-10G'], 20, 'PO-KKM-2026-001', 'GR-KKM-2026-001', 8);
            $this->seedPurchaseOrder($suppliers['Sumber Rejeki Oli'], $owner, $parts['OLI-MPX2-08'], 24, 'PO-KKM-2026-002', 'GR-KKM-2026-002', 12);

            $workOrderService = app(WorkOrderService::class);
            $order = WorkOrder::firstOrCreate(['number' => 'WO-KKM-2026-001'], [
                'vehicle_id' => $vehicles['B 3142 KKM']->id,
                'mechanic_id' => $mechanic->id,
                'complaint' => 'Servis berkala, tarikan awal terasa berat dan rem depan kurang pakem.',
                'status' => 'in_progress',
                'service_fee' => 75000,
                'notes' => 'Demo: pemeriksaan CVT, ganti roller 10 gram, oli mesin, dan kampas rem depan.',
            ]);
            if ($order->items()->doesntExist()) {
                $workOrderService->addItem($order, ['spare_part_id' => $parts['CVT-RLR-10G']->id, 'quantity' => 1], $mechanic->id);
                $workOrderService->addItem($order, ['spare_part_id' => $parts['OLI-MPX2-08']->id, 'quantity' => 1], $mechanic->id);
                $workOrderService->addItem($order, ['spare_part_id' => $parts['BRK-KP-HB']->id, 'quantity' => 1], $mechanic->id);
            }

            WorkOrder::firstOrCreate(['number' => 'WO-KKM-2026-002'], [
                'vehicle_id' => $vehicles['B 2781 KEN']->id,
                'mechanic_id' => $mechanic->id,
                'complaint' => 'V-belt retak dan minta ganti oli gardan.',
                'status' => 'open',
                'service_fee' => 50000,
                'notes' => 'Menunggu persetujuan pemilik kendaraan.',
            ]);
            WorkOrder::firstOrCreate(['number' => 'WO-KKM-2026-003'], [
                'vehicle_id' => $vehicles['B 4509 NKN']->id,
                'mechanic_id' => $mechanic->id,
                'complaint' => 'Mesin sulit langsam, cek busi dan filter udara.',
                'status' => 'completed',
                'service_fee' => 45000,
                'completed_at' => now()->subDay(),
                'notes' => 'Busi dibersihkan dan filter udara diperiksa.',
            ]);

            $shipment = Shipment::firstOrCreate(['number' => 'KIRIM-KKM-2026-001'], [
                'work_order_id' => $order->id,
                'recipient_name' => 'Rina Amelia',
                'phone' => '0812-1111-2201',
                'address' => 'Jl. Kenanga No. 14, Jakarta Timur',
                'courier' => 'Kurir Bengkel',
                'tracking_number' => 'KKM-TRK-0001',
                'status' => 'shipped',
                'user_id' => $owner->id,
                'notes' => 'Pengantaran motor setelah servis.',
            ]);
            if ($shipment->statusLogs()->doesntExist()) {
                ShipmentStatusLog::create(['shipment_id' => $shipment->id, 'status' => 'pending', 'notes' => 'Pesanan pengiriman dibuat.', 'user_id' => $owner->id]);
                ShipmentStatusLog::create(['shipment_id' => $shipment->id, 'status' => 'shipped', 'notes' => 'Motor dibawa kurir bengkel.', 'user_id' => $owner->id]);
            }

            $opname = StockOpname::firstOrCreate(['number' => 'SO-KKM-2026-001'], [
                'opname_date' => now()->toDateString(),
                'status' => 'completed',
                'user_id' => $owner->id,
                'notes' => 'Stock opname demo untuk roller, oli mesin, dan kampas rem.',
            ]);
            foreach ([$parts['CVT-RLR-10G'], $parts['OLI-MPX2-08'], $parts['BRK-KP-HB']] as $part) {
                $stock = (int) $part->fresh()->stock;
                $opname->items()->firstOrCreate(['spare_part_id' => $part->id], [
                    'system_stock' => $stock,
                    'physical_stock' => $stock,
                    'difference' => 0,
                    'notes' => 'Jumlah fisik sesuai stok sistem.',
                ]);
            }
        });
    }

    private function seedPurchaseOrder(Supplier $supplier, User $owner, SparePart $part, int $quantity, string $orderNumber, string $receiptNumber, int $receiveQuantity): void
    {
        $order = PurchaseOrder::firstOrCreate(['number' => $orderNumber], [
            'supplier_id' => $supplier->id,
            'order_date' => now()->subDays(2)->toDateString(),
            'status' => 'ordered',
            'user_id' => $owner->id,
            'notes' => 'PO demo untuk pengisian stok rutin KEN KEN Motor.',
        ]);
        $item = $order->items()->firstOrCreate(['spare_part_id' => $part->id], [
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_price' => $part->purchase_price,
        ]);

        if (GoodsReceipt::where('number', $receiptNumber)->doesntExist()) {
            app(ProcurementService::class)->receive([
                'number' => $receiptNumber,
                'purchase_order_id' => $order->id,
                'received_date' => now()->subDay()->toDateString(),
                'notes' => 'Penerimaan sebagian barang demo.',
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => $receiveQuantity]],
            ], $owner->id);
        }
    }
}
