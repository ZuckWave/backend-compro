<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_category_crud_records_old_and_new_values(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner, 'sanctum');
        $id = $this->postJson('/api/categories', ['name' => 'Oli'])->assertCreated()->json('data.id');

        $this->patchJson('/api/categories/'.$id, ['name' => 'Pelumas'])->assertOk()->assertJsonPath('data.name', 'Pelumas');

        $log = AuditLog::query()->where('table_name', 'categories')->where('record_id', $id)->where('action', 'updated')->firstOrFail();
        $this->assertSame('Oli', $log->old_values['name']);
        $this->assertSame('Pelumas', $log->new_values['name']);
        $this->assertSame($owner->id, $log->user_id);

        $this->deleteJson('/api/categories/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['table_name' => 'categories', 'record_id' => $id, 'action' => 'deleted']);
    }

    public function test_referenced_category_cannot_be_deleted(): void
    {
        $part = SparePart::factory()->create();
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $this->deleteJson('/api/categories/'.$part->category_id)->assertConflict();

        $this->assertDatabaseHas('categories', ['id' => $part->category_id]);
        $this->assertModelExists($part);
    }

    public function test_owner_creates_user_with_hashed_password_and_explicit_role(): void
    {
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $response = $this->postJson('/api/users', [
            'name' => 'Mekanik Baru', 'email' => 'mekanik@example.com', 'role' => 'mekanik',
            'password' => 'secret-123', 'password_confirmation' => 'secret-123',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'mekanik')->assertJsonMissingPath('data.password');
        $this->assertTrue(Hash::check('secret-123', User::findOrFail($response->json('data.id'))->password));
    }

    public function test_last_owner_cannot_be_demoted(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner, 'sanctum');

        $this->patchJson('/api/users/'.$owner->id, ['role' => 'mekanik'])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner']);
    }

    public function test_owner_can_reset_password_and_revoke_existing_tokens(): void
    {
        $owner = User::factory()->owner()->create();
        $mechanic = User::factory()->create();
        $mechanic->createToken('old');
        $this->actingAs($owner, 'sanctum');

        $this->patchJson('/api/users/'.$mechanic->id, ['password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'])->assertOk()->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('new-secret-123', $mechanic->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_stock_report_dashboard_and_supplier_report_include_aggregates(): void
    {
        SparePart::factory()->create(['stock' => 5, 'min_stock' => 5, 'purchase_price' => '10.10', 'selling_price' => '12.50']);
        Supplier::create(['name' => 'Supplier A']);
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $this->getJson('/api/reports/stock')->assertOk()->assertJsonPath('summary.stock_quantity', 5)->assertJsonPath('summary.purchase_value', '50.50')->assertJsonPath('summary.selling_value', '62.50');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.low_stock_count', 1);
        $this->getJson('/api/reports/suppliers')->assertOk()->assertJsonPath('data.0.purchase_orders_count', 0);
        $this->getJson('/api/reports/purchases')->assertOk()->assertJsonPath('summary.received_value', '0.00');
    }

    public function test_search_input_is_bound_and_pagination_is_limited(): void
    {
        Category::factory()->create(['name' => 'Oli']);
        $this->actingAs(User::factory()->owner()->create(), 'sanctum');

        $this->getJson('/api/categories?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/categories?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/reports/purchases?from=2026-10-09&to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_owner_command_creates_account_without_default_credentials(): void
    {
        $this->artisan('compro:create-owner', ['--name' => 'Owner', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password (minimal 8 karakter)', 'owner-secret-123')
            ->expectsOutput('Akun owner berhasil dibuat.')
            ->assertExitCode(0);

        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame('owner', $owner->role);
        $this->assertTrue(Hash::check('owner-secret-123', $owner->password));
    }
}
