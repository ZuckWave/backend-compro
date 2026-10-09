<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function order(array $parts): array
    {
        $supplier = Supplier::create(['name' => 'Supplier Test']);
        $response = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id, 'order_date' => '2026-10-01',
            'items' => array_map(fn (SparePart $part): array => ['spare_part_id' => $part->id, 'quantity' => 10, 'unit_price' => '12500.25'], $parts),
        ])->assertCreated();
        $id = $response->json('data.id');
        $this->patchJson('/api/purchase-orders/'.$id.'/status', ['status' => 'ordered'])->assertOk();

        return $response->json('data');
    }

    public function test_partial_and_final_receipts_update_stock_and_order_status(): void
    {
        $part = SparePart::factory()->create();
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $order = $this->order([$part]);

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $order['id'], 'received_date' => '2026-10-09',
            'items' => [['purchase_order_item_id' => $order['items'][0]['id'], 'quantity' => 4]],
        ])->assertCreated()->assertJsonPath('data.total', '50001.00');

        $this->assertDatabaseHas('purchase_orders', ['id' => $order['id'], 'status' => 'partial']);
        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 4]);

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $order['id'], 'received_date' => '2026-10-09',
            'items' => [['purchase_order_item_id' => $order['items'][0]['id'], 'quantity' => 6]],
        ])->assertCreated();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order['id'], 'status' => 'received']);
        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 10]);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $order['items'][0]['id'], 'received_quantity' => 10]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_over_receipt_rolls_back_stock_documents_and_audits(): void
    {
        $parts = [SparePart::factory()->create(), SparePart::factory()->create()];
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $order = $this->order($parts);
        $auditCount = AuditLog::query()->count();

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $order['id'], 'received_date' => '2026-10-09',
            'items' => [
                ['purchase_order_item_id' => $order['items'][0]['id'], 'quantity' => 4],
                ['purchase_order_item_id' => $order['items'][1]['id'], 'quantity' => 11],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('spare_parts', ['id' => $parts[0]->id, 'stock' => 0]);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $order['items'][0]['id'], 'received_quantity' => 0]);
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_receipt_rejects_an_item_from_another_purchase_order(): void
    {
        $part = SparePart::factory()->create();
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $first = $this->order([$part]);
        $second = $this->order([$part]);

        $this->postJson('/api/goods-receipts', [
            'purchase_order_id' => $first['id'], 'received_date' => '2026-10-09',
            'items' => [['purchase_order_item_id' => $second['items'][0]['id'], 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 0]);
    }

    public function test_ordered_purchase_orders_cannot_be_edited_or_deleted(): void
    {
        $part = SparePart::factory()->create();
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $order = $this->order([$part]);

        $this->patchJson('/api/purchase-orders/'.$order['id'], ['notes' => 'change'])->assertUnprocessable();
        $this->deleteJson('/api/purchase-orders/'.$order['id'])->assertUnprocessable();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order['id'], 'status' => 'ordered', 'notes' => null]);
    }

    public function test_duplicate_parts_return_422_without_creating_purchase_order(): void
    {
        $part = SparePart::factory()->create();
        $supplier = Supplier::create(['name' => 'Supplier Test']);
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $row = ['spare_part_id' => $part->id, 'quantity' => 1, 'unit_price' => 100];

        $this->postJson('/api/purchase-orders', ['supplier_id' => $supplier->id, 'order_date' => '2026-10-09', 'items' => [$row, $row]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.spare_part_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }
}
