<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SparePart;
use App\Models\User;
use Database\Seeders\WorkshopSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class WorkshopSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_migrate_with_workshop_seeder_creates_two_logins_and_linked_workshop_records(): void
    {
        $this->configurePasswords();
        $this->assertDatabaseCount('users', 0);

        $this->artisan('migrate', ['--seed' => true, '--seeder' => 'WorkshopSeeder', '--no-interaction' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 2);
        foreach ([
            'categories', 'suppliers', 'spare_parts', 'vehicles', 'purchase_orders', 'purchase_order_items',
            'goods_receipts', 'goods_receipt_items', 'work_orders', 'work_order_items', 'stock_opnames',
            'stock_opname_items', 'shipments',
        ] as $table) {
            $this->assertDatabaseCount($table, 10);
        }
        $this->assertDatabaseCount('stock_movements', 40);
        $this->assertDatabaseCount('shipment_status_logs', 21);
        $this->assertGreaterThanOrEqual(10, AuditLog::query()->count());

        $part = SparePart::query()->where('code', 'KKM-CVT-PULLEY-BEAT')->firstOrFail();
        $this->assertSame('Pulley Depan Honda BeAT FI', $part->name);
        $this->assertSame(15, $part->stock);
        $this->assertDatabaseHas('suppliers', ['id' => $part->supplier_id, 'name' => 'Mitra Transmisi Nusantara']);
        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-KKM-AWAL-003', 'status' => 'partial']);
        $this->assertDatabaseHas('shipments', ['number' => 'SH-KKM-AWAL-001', 'status' => 'delivered']);

        $this->postJson('/api/login', ['email' => WorkshopSeeder::OWNER_EMAIL, 'password' => 'test-owner-password'])
            ->assertOk()->assertJsonPath('user.role', 'owner')->assertJsonStructure(['token']);
        $this->postJson('/api/login', ['email' => WorkshopSeeder::MECHANIC_EMAIL, 'password' => 'test mechanic password'])
            ->assertOk()->assertJsonPath('user.role', 'mekanik')->assertJsonStructure(['token']);
    }

    public function test_seeding_again_preserves_passwords_and_existing_business_records(): void
    {
        $this->configurePasswords();
        $this->seed(WorkshopSeeder::class);
        $owner = User::query()->where('email', WorkshopSeeder::OWNER_EMAIL)->firstOrFail();
        $part = SparePart::query()->where('code', 'KKM-CVT-PULLEY-BEAT')->firstOrFail();
        $owner->update(['password' => 'new-owner-password']);
        $part->update(['code' => 'RAK-A-PULLEY-BEAT', 'name' => 'Pulley Depan BeAT FI - Rak A']);

        $this->seed(WorkshopSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('purchase_orders', 10);
        $this->assertDatabaseCount('stock_movements', 40);
        $this->assertDatabaseCount('shipment_status_logs', 21);
        $this->assertSame('Pulley Depan BeAT FI - Rak A', $part->refresh()->name);
        $this->assertSame('RAK-A-PULLEY-BEAT', $part->code);
        $this->assertSame(15, $part->stock);
        $this->assertTrue(Hash::check('new-owner-password', $owner->refresh()->password));
    }

    public function test_missing_mechanic_password_rolls_back_the_first_account_and_seed_data(): void
    {
        $this->configurePasswords();
        config(['workshop.mechanic_password' => null]);

        try {
            $this->seed(WorkshopSeeder::class);
            $this->fail('Seeding must fail without a mechanic password.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('WORKSHOP_MECHANIC_PASSWORD', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('spare_parts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_seed_refuses_to_change_an_existing_accounts_role(): void
    {
        $this->configurePasswords();
        $existingUser = User::factory()->create(['email' => WorkshopSeeder::OWNER_EMAIL]);

        try {
            $this->seed(WorkshopSeeder::class);
            $this->fail('Seeding must fail when an account has a different role.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('role berbeda', $exception->getMessage());
        }

        $this->assertSame('mekanik', $existingUser->refresh()->role);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('spare_parts', 0);
    }

    private function configurePasswords(): void
    {
        config([
            'workshop.owner_password' => 'test-owner-password',
            'workshop.mechanic_password' => 'test mechanic password',
        ]);
    }
}
