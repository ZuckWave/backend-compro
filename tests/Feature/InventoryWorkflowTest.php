<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_opening_stock_creates_a_movement_and_low_stock_notification(): void
    {
        $owner = User::factory()->owner()->create();
        $category = Category::factory()->create();
        $this->actingAs($owner, 'sanctum');

        $response = $this->postJson('/api/spare-parts', [
            'code' => 'OLI-01', 'name' => 'Oli Mesin', 'category_id' => $category->id,
            'unit' => 'botol', 'purchase_price' => '30000.00', 'selling_price' => '45000.00',
            'opening_stock' => 5, 'min_stock' => 5,
        ]);

        $response->assertCreated()->assertJsonPath('data.stock', 5)->assertJsonPath('data.is_low_stock', true);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('stock_movements', ['spare_part_id' => $id, 'type' => 'in', 'quantity' => 5, 'stock_before' => 0, 'stock_after' => 5, 'user_id' => $owner->id]);
        $this->getJson('/api/minimum-stock')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_returns_422_for_direct_stock_updates(): void
    {
        $part = SparePart::factory()->create(['stock' => 7]);
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $this->patchJson('/api/spare-parts/'.$part->id, ['stock' => 999])->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 7]);
    }

    public function test_opname_records_system_and_physical_stock_with_signed_difference(): void
    {
        $part = SparePart::factory()->create(['stock' => 10]);
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $this->postJson('/api/stock-opnames', [
            'opname_date' => '2026-10-09',
            'items' => [['spare_part_id' => $part->id, 'system_stock' => 10, 'physical_stock' => 8]],
        ])->assertCreated()->assertJsonPath('data.items.0.difference', -2);

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 8]);
        $this->assertDatabaseHas('stock_movements', ['spare_part_id' => $part->id, 'type' => 'opname', 'quantity' => -2, 'stock_before' => 10, 'stock_after' => 8]);
    }

    public function test_stale_opname_rolls_back_every_item_and_audit_entry(): void
    {
        $first = SparePart::factory()->create(['stock' => 10]);
        $second = SparePart::factory()->create(['stock' => 5]);
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $auditCount = AuditLog::query()->count();

        $this->postJson('/api/stock-opnames', [
            'opname_date' => '2026-10-09',
            'items' => [
                ['spare_part_id' => $first->id, 'system_stock' => 10, 'physical_stock' => 8],
                ['spare_part_id' => $second->id, 'system_stock' => 4, 'physical_stock' => 2],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('spare_parts', ['id' => $first->id, 'stock' => 10]);
        $this->assertDatabaseHas('spare_parts', ['id' => $second->id, 'stock' => 5]);
        $this->assertDatabaseCount('stock_opnames', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_duplicate_opname_parts_return_422(): void
    {
        $part = SparePart::factory()->create(['stock' => 3]);
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $this->postJson('/api/stock-opnames', [
            'opname_date' => '2026-10-09',
            'items' => [
                ['spare_part_id' => $part->id, 'system_stock' => 3, 'physical_stock' => 2],
                ['spare_part_id' => $part->id, 'system_stock' => 3, 'physical_stock' => 2],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.spare_part_id');

        $this->assertDatabaseCount('stock_opnames', 0);
    }

    public function test_stock_history_and_audit_log_are_read_only(): void
    {
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $this->postJson('/api/stock-in', ['quantity' => 100])->assertMethodNotAllowed();
        $this->postJson('/api/stock-out', ['quantity' => 100])->assertMethodNotAllowed();
        $this->postJson('/api/audit-logs', ['action' => 'fake'])->assertMethodNotAllowed();
    }
}
