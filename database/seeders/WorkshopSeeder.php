<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\ShipmentService;
use App\Services\WorkOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkshopSeeder extends Seeder
{
    public const OWNER_EMAIL = 'owner.bengkel@kenkenmotor.test';

    public const MECHANIC_EMAIL = 'mekanik.bengkel@kenkenmotor.test';

    /** @var list<array{string, string, string, string, string, int, int, int, int, string, string, string, string}> */
    private const CATALOG = [
        ['CVT', 'Mitra Transmisi Nusantara', 'CVT-PULLEY-BEAT',
            'Pulley Depan Honda BeAT FI', 'set', 105000, 145000, 11, 4,
            'Honda', 'BeAT FI', 'Tarikan awal tersendat; periksa pulley depan.', 'Cicendo'],
        ['Rem', 'Sarana Rem Motor', 'REM-DEPAN-VARIO',
            'Kampas Rem Depan Honda Vario 125', 'set', 35000, 52000, 5, 10,
            'Honda', 'Vario 125', 'Rem depan berbunyi saat pengereman.', 'Antapani'],
        ['Pelumas', 'Sentra Pelumas Bandung', 'OLI-MPX2-08',
            'Oli Mesin Honda MPX2 0,8 L', 'botol', 47000, 62000, 25, 10,
            'Honda', 'Scoopy', 'Penggantian oli mesin berkala.', 'Lengkong'],
        ['Filter', 'Mitra Filter Otomotif', 'FILTER-UDARA-MIO',
            'Filter Udara Yamaha Mio M3', 'pcs', 28500, 42000, 6, 5,
            'Yamaha', 'Mio M3', 'Filter udara kotor; mesin kurang responsif.', 'Kiaracondong'],
        ['Pengapian', 'Sarana Pengapian Motor', 'BUSI-NGK-CPR',
            'Busi NGK CPR9EA-9', 'pcs', 20500, 31000, 16, 7,
            'Honda', 'Supra X 125 FI', 'Mesin sulit hidup saat dingin; periksa busi.', 'Batununggal'],
        ['Ban', 'Sentra Ban Roda Dua', 'BAN-TL-809014',
            'Ban Tubeless 80/90-14', 'pcs', 175000, 225000, 6, 4,
            'Honda', 'BeAT', 'Tapak ban depan menipis.', 'Arcamanik'],
        ['Kelistrikan', 'Mitra Aki dan Kelistrikan', 'AKI-YTZ5S',
            'Aki Motor YTZ5S', 'pcs', 210000, 265000, 4, 5,
            'Honda', 'Vario 125', 'Starter elektrik melemah; periksa aki.', 'Cibiru'],
        ['Rantai dan Gir', 'Sarana Rantai Motor', 'RANTAI-428H-104',
            'Rantai Motor 428H 104 Mata', 'pcs', 110000, 150000, 9, 4,
            'Honda', 'Supra X 125', 'Rantai aus dan berbunyi saat berjalan.', 'Ujungberung'],
        ['Suspensi', 'Mitra Suspensi Roda Dua', 'SUSP-BELAKANG-NMAX',
            'Shockbreaker Belakang Yamaha NMAX', 'set', 360000, 470000, 3, 4,
            'Yamaha', 'NMAX', 'Suspensi belakang memantul berlebihan.', 'Sukasari'],
        ['Pendingin', 'Sentra Pendingin Mesin', 'COOLANT-1L',
            'Coolant Radiator Motor 1 L', 'botol', 32000, 48000, 14, 6,
            'Yamaha', 'Aerox 155', 'Cairan radiator berkurang; periksa sistem pendingin.', 'Coblong'],
    ];

    public function __construct(
        private InventoryService $inventory,
        private ProcurementService $procurement,
        private WorkOrderService $workOrders,
        private ShipmentService $shipments,
    ) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $owner = $this->account(self::OWNER_EMAIL, 'Owner Bengkel', 'owner', 'WORKSHOP_OWNER_PASSWORD', 'workshop.owner_password');
            $mechanic = $this->account(self::MECHANIC_EMAIL, 'Mekanik Bengkel', 'mekanik', 'WORKSHOP_MECHANIC_PASSWORD', 'workshop.mechanic_password');

            foreach (self::CATALOG as $index => $row) {
                $this->seedCatalogEntry($row, $index + 1, $owner->id, $mechanic->id);
            }
        }, 3);
    }

    private function account(string $email, string $name, string $role, string $variable, string $configKey): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $password = config($configKey);
            if (! is_string($password) || mb_strlen($password) < 8 || strlen($password) > 72 || trim($password) === '') {
                throw new RuntimeException("Isi {$variable} dengan password minimal 8 karakter dan maksimal 72 byte sebelum menjalankan WorkshopSeeder.");
            }

            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'role' => $role,
                'password' => $password,
            ]);
        }

        if ($user->role !== $role) {
            throw new RuntimeException("Akun {$email} sudah ada dengan role berbeda; role akun tersebut tidak diubah.");
        }

        return $user;
    }

    /** @param array{string, string, string, string, string, int, int, int, int, string, string, string, string} $row */
    private function seedCatalogEntry(array $row, int $sequence, int $ownerId, int $mechanicId): void
    {
        [$categoryName, $supplierName, $sku, $partName, $unit, $purchasePrice, $sellingPrice,
            $openingStock, $minimumStock, $brand, $vehicleType, $complaint, $district] = $row;

        $suffix = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $orderNumber = 'PO-KKM-AWAL-'.$suffix;
        if (PurchaseOrder::query()->where('number', $orderNumber)->exists()) {
            return;
        }

        $category = Category::firstOrCreate(['name' => $categoryName]);
        $supplier = Supplier::firstOrCreate(['name' => $supplierName]);
        $part = $this->inventory->createPart([
            'code' => 'KKM-'.$sku,
            'name' => $partName,
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'unit' => $unit,
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'opening_stock' => $openingStock,
            'min_stock' => $minimumStock,
        ], $ownerId);

        $vehicle = Vehicle::create([
            'owner_name' => 'Pelanggan '.$suffix,
            'plate_number' => 'B '.(7100 + $sequence).' KKM',
            'brand' => $brand,
            'type' => $vehicleType,
            'year' => 2022,
        ]);

        $purchaseOrder = $this->procurement->createOrder([
            'number' => $orderNumber,
            'supplier_id' => $supplier->id,
            'order_date' => today()->subDays(2)->toDateString(),
            'items' => [['spare_part_id' => $part->id, 'quantity' => 6, 'unit_price' => $purchasePrice]],
        ], $ownerId);
        $this->procurement->transition($purchaseOrder, 'ordered');
        $this->procurement->receive([
            'number' => 'GR-KKM-AWAL-'.$suffix,
            'purchase_order_id' => $purchaseOrder->id,
            'received_date' => today()->subDay()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $purchaseOrder->items->firstOrFail()->id,
                'quantity' => $sequence % 3 === 0 ? 4 : 6,
            ]],
        ], $ownerId);

        $workOrder = $this->workOrders->create([
            'number' => 'WO-KKM-AWAL-'.$suffix,
            'vehicle_id' => $vehicle->id,
            'mechanic_id' => $mechanicId,
            'complaint' => $complaint,
            'status' => 'in_progress',
            'service_fee' => 25000 + $sequence * 2500,
        ]);
        $this->workOrders->addItem($workOrder, ['spare_part_id' => $part->id, 'quantity' => 1], $mechanicId);
        if ($sequence <= 8) {
            $this->workOrders->update($workOrder, ['status' => 'completed'], $mechanicId);
        }

        $part->refresh();
        $this->inventory->opname([
            'number' => 'SO-KKM-AWAL-'.$suffix,
            'opname_date' => today()->toDateString(),
            'items' => [[
                'spare_part_id' => $part->id,
                'system_stock' => $part->stock,
                'physical_stock' => $part->stock - 1,
                'notes' => 'Selisih satu unit pada penghitungan stok.',
            ]],
        ], $ownerId);

        $shipment = $this->shipments->create([
            'number' => 'SH-KKM-AWAL-'.$suffix,
            'work_order_id' => $workOrder->id,
            'recipient_name' => $vehicle->owner_name,
            'address' => 'Area '.$district.', Bandung',
            'courier' => 'Kurir Bengkel',
            'tracking_number' => 'KKM-AWAL-'.$suffix,
        ], $ownerId);
        if ($sequence <= 7) {
            $shipment = $this->shipments->transition($shipment, ['status' => 'shipped'], $ownerId);
        }
        if ($sequence <= 4) {
            $this->shipments->transition($shipment, ['status' => 'delivered'], $ownerId);
        }
    }
}
