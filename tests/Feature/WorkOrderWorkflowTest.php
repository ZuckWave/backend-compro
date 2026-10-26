<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WorkOrderWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function order(User $mechanic): array
    {
        $vehicle = Vehicle::create(['owner_name' => 'Pelanggan', 'plate_number' => 'B'.fake()->unique()->numerify('#####'), 'brand' => 'Honda', 'type' => 'Beat']);

        return $this->postJson('/api/work-orders', [
            'vehicle_id' => $vehicle->id, 'mechanic_id' => $mechanic->id,
            'complaint' => 'Ganti oli', 'service_fee' => '20000.00',
        ])->assertCreated()->json('data');
    }

    public function test_using_parts_reduces_stock_and_snapshots_price(): void
    {
        $mechanic = User::factory()->create();
        $part = SparePart::factory()->create(['stock' => 5, 'selling_price' => '15000.25']);
        $this->actingAs($mechanic, 'sanctum');
        $order = $this->order($mechanic);

        $this->postJson('/api/work-orders/'.$order['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 2])
            ->assertCreated()->assertJsonPath('data.unit_price', '15000.25');

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 3]);
        $this->assertDatabaseHas('stock_movements', ['spare_part_id' => $part->id, 'type' => 'out', 'quantity' => 2, 'stock_before' => 5, 'stock_after' => 3]);
        $this->getJson('/api/work-orders/'.$order['id'])->assertOk()->assertJsonPath('data.total', '50000.50');
    }

    public function test_insufficient_stock_returns_422_and_rolls_back_item_and_audit(): void
    {
        $mechanic = User::factory()->create();
        $part = SparePart::factory()->create(['stock' => 1]);
        $this->actingAs($mechanic, 'sanctum');
        $order = $this->order($mechanic);
        $auditCount = AuditLog::query()->count();

        $this->postJson('/api/work-orders/'.$order['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 1]);
        $this->assertDatabaseCount('work_order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_quantity_correction_and_item_deletion_restore_stock(): void
    {
        $mechanic = User::factory()->create();
        $part = SparePart::factory()->create(['stock' => 5]);
        $this->actingAs($mechanic, 'sanctum');
        $order = $this->order($mechanic);
        $item = $this->postJson('/api/work-orders/'.$order['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 3])->assertCreated()->json('data');
        $url = '/api/work-orders/'.$order['id'].'/items/'.$item['id'];

        $this->patchJson($url, ['quantity' => 1])->assertOk()->assertJsonPath('data.quantity', 1);
        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 4]);

        $this->deleteJson($url)->assertNoContent();

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 5]);
        $this->assertDatabaseMissing('work_order_items', ['id' => $item['id']]);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_completed_order_cannot_consume_more_parts_or_complete_twice(): void
    {
        $mechanic = User::factory()->create();
        $part = SparePart::factory()->create(['stock' => 3]);
        $this->actingAs($mechanic, 'sanctum');
        $order = $this->order($mechanic);
        $this->postJson('/api/work-orders/'.$order['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 1])->assertCreated();

        $this->patchJson('/api/work-orders/'.$order['id'], ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->patchJson('/api/work-orders/'.$order['id'], ['status' => 'completed'])->assertUnprocessable();
        $this->postJson('/api/work-orders/'.$order['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 1])->assertUnprocessable();

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 2]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertNotNull(WorkOrder::findOrFail($order['id'])->completed_at);
    }

    public function test_cancelling_order_restores_stock_once_and_excludes_cancelled_usage(): void
    {
        $owner = User::factory()->owner()->create();
        $mechanic = User::factory()->create();
        $part = SparePart::factory()->create(['stock' => 5]);
        $this->actingAs($owner, 'sanctum');
        $order = $this->order($mechanic);
        $this->postJson('/api/work-orders/'.$order['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 2])->assertCreated();

        $this->patchJson('/api/work-orders/'.$order['id'], ['status' => 'cancelled'])->assertOk();
        $this->patchJson('/api/work-orders/'.$order['id'], ['status' => 'cancelled'])->assertUnprocessable();

        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 5]);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->getJson('/api/reports/spare-part-usage')->assertOk()->assertJsonPath('summary.quantity', 0)->assertJsonPath('summary.value', '0.00');
    }

    public function test_nested_item_from_other_work_order_returns_404(): void
    {
        $mechanic = User::factory()->create();
        $part = SparePart::factory()->create(['stock' => 5]);
        $this->actingAs($mechanic, 'sanctum');
        $first = $this->order($mechanic);
        $second = $this->order($mechanic);
        $item = $this->postJson('/api/work-orders/'.$first['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 1])->assertCreated()->json('data');

        $this->patchJson('/api/work-orders/'.$second['id'].'/items/'.$item['id'], ['quantity' => 2])->assertNotFound();

        $this->assertDatabaseHas('work_order_items', ['id' => $item['id'], 'quantity' => 1]);
        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 4]);
    }

    public function test_mechanic_can_only_view_and_update_assigned_work_orders(): void
    {
        $assignedMechanic = User::factory()->create();
        $otherMechanic = User::factory()->create();
        $this->actingAs($assignedMechanic, 'sanctum');
        $assignedOrder = $this->order($assignedMechanic);
        $this->actingAs($otherMechanic, 'sanctum');
        $otherOrder = $this->order($otherMechanic);
        $this->actingAs($assignedMechanic, 'sanctum');

        $this->getJson('/api/work-orders')->assertOk()->assertJsonFragment(['number' => $assignedOrder['number']])->assertJsonMissing(['number' => $otherOrder['number']]);
        $this->getJson('/api/work-orders/'.$otherOrder['id'])->assertNotFound();
        $this->patchJson('/api/work-orders/'.$otherOrder['id'], ['status' => 'cancelled'])->assertNotFound();

        $this->assertDatabaseHas('work_orders', ['id' => $otherOrder['id'], 'status' => 'open']);
    }

    public function test_mechanic_cannot_access_items_or_usage_for_another_mechanics_work_order(): void
    {
        $assignedMechanic = User::factory()->create();
        $otherMechanic = User::factory()->create();
        $part = SparePart::factory()->create(['stock' => 5]);
        $this->actingAs($otherMechanic, 'sanctum');
        $otherOrder = $this->order($otherMechanic);
        $item = $this->postJson('/api/work-orders/'.$otherOrder['id'].'/items', ['spare_part_id' => $part->id, 'quantity' => 2])
            ->assertCreated()->json('data');

        $this->actingAs($assignedMechanic, 'sanctum');
        $this->getJson('/api/work-orders/'.$otherOrder['id'].'/items')->assertNotFound();
        $this->getJson('/api/work-orders/'.$otherOrder['id'].'/items/'.$item['id'])->assertNotFound();
        $this->patchJson('/api/work-orders/'.$otherOrder['id'].'/items/'.$item['id'], ['quantity' => 1])->assertNotFound();
        $this->deleteJson('/api/work-orders/'.$otherOrder['id'].'/items/'.$item['id'])->assertNotFound();
        $this->getJson('/api/spare-part-usage')->assertOk()->assertJsonMissing(['work_order_id' => $otherOrder['id']]);

        $this->assertDatabaseHas('work_order_items', ['id' => $item['id'], 'quantity' => 2]);
        $this->assertDatabaseHas('spare_parts', ['id' => $part->id, 'stock' => 3]);
    }

    public function test_mechanic_cannot_assign_a_work_order_to_another_mechanic(): void
    {
        $mechanic = User::factory()->create();
        $otherMechanic = User::factory()->create();
        $vehicle = Vehicle::create(['owner_name' => 'Pelanggan', 'plate_number' => 'B7821KKM', 'brand' => 'Honda', 'type' => 'BeAT']);
        $this->actingAs($mechanic, 'sanctum');

        $this->postJson('/api/work-orders', [
            'vehicle_id' => $vehicle->id,
            'mechanic_id' => $otherMechanic->id,
            'complaint' => 'Servis CVT',
        ])->assertUnprocessable()->assertJsonValidationErrors('mechanic_id');

        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_owner_can_view_work_orders_assigned_to_any_mechanic(): void
    {
        $owner = User::factory()->owner()->create();
        $mechanic = User::factory()->create();
        $this->actingAs($owner, 'sanctum');
        $order = $this->order($mechanic);

        $this->getJson('/api/work-orders')->assertOk()->assertJsonFragment(['number' => $order['number']]);
        $this->getJson('/api/work-orders/'.$order['id'])->assertOk();
    }

    public function test_owner_cannot_be_assigned_as_mechanic(): void
    {
        $owner = User::factory()->owner()->create();
        $vehicle = Vehicle::create(['owner_name' => 'Test', 'plate_number' => 'B1234ABC', 'brand' => 'Honda', 'type' => 'Beat']);
        $this->actingAs($owner, 'sanctum');

        $this->postJson('/api/work-orders', ['vehicle_id' => $vehicle->id, 'mechanic_id' => $owner->id, 'complaint' => 'Ganti oli'])
            ->assertUnprocessable()->assertJsonValidationErrors('mechanic_id');

        $this->assertDatabaseCount('work_orders', 0);
    }
}
