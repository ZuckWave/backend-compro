<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ShipmentWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_delivery_progress_creates_status_history(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner, 'sanctum');
        $shipment = $this->postJson('/api/shipments', ['recipient_name' => 'Pelanggan', 'address' => 'Bandung'])->assertCreated()->json('data');

        $this->postJson('/api/shipments/'.$shipment['id'].'/statuses', ['status' => 'shipped', 'notes' => 'Dalam perjalanan'])->assertOk()->assertJsonPath('data.status', 'shipped');
        $this->postJson('/api/shipments/'.$shipment['id'].'/statuses', ['status' => 'delivered', 'notes' => 'Diterima pelanggan'])->assertOk()->assertJsonPath('data.status', 'delivered');

        $this->assertDatabaseHas('shipments', ['id' => $shipment['id'], 'status' => 'delivered']);
        $this->assertDatabaseCount('shipment_status_logs', 3);
        $this->getJson('/api/shipments/'.$shipment['id'].'/statuses')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_invalid_status_transition_does_not_append_history(): void
    {
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $shipment = $this->postJson('/api/shipments', ['recipient_name' => 'Pelanggan', 'address' => 'Bandung'])->assertCreated()->json('data');

        $this->postJson('/api/shipments/'.$shipment['id'].'/statuses', ['status' => 'delivered'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('shipments', ['id' => $shipment['id'], 'status' => 'pending']);
        $this->assertDatabaseCount('shipment_status_logs', 1);
    }

    public function test_status_cannot_be_set_through_general_shipment_update(): void
    {
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $shipment = $this->postJson('/api/shipments', ['recipient_name' => 'Pelanggan', 'address' => 'Bandung'])->assertCreated()->json('data');

        $this->patchJson('/api/shipments/'.$shipment['id'], ['status' => 'delivered'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('shipments', ['id' => $shipment['id'], 'status' => 'pending']);
    }

    public function test_cancellation_is_terminal_and_keeps_history(): void
    {
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');
        $shipment = $this->postJson('/api/shipments', ['recipient_name' => 'Pelanggan', 'address' => 'Bandung'])->assertCreated()->json('data');

        $this->postJson('/api/shipments/'.$shipment['id'].'/statuses', ['status' => 'cancelled'])->assertOk();
        $this->postJson('/api/shipments/'.$shipment['id'].'/statuses', ['status' => 'shipped'])->assertUnprocessable();
        $this->patchJson('/api/shipments/'.$shipment['id'], ['address' => 'Jakarta'])->assertUnprocessable();

        $this->assertDatabaseHas('shipments', ['id' => $shipment['id'], 'status' => 'cancelled', 'address' => 'Bandung']);
        $this->assertDatabaseCount('shipment_status_logs', 2);
    }
}
