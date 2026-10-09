<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_without_a_token(): void
    {
        $this->getJson('/api/spare-parts')->assertUnauthorized();
    }

    public function test_valid_credentials_issue_token_and_hide_password(): void
    {
        $user = User::factory()->owner()->create(['password' => Hash::make('secret-123')]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-123']);

        $response->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('user.role', 'owner')->assertJsonMissingPath('user.password');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->withToken($response->json('token'))->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_returns_422_for_incorrect_credentials_without_issuing_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonPath('errors.email.0', 'Email atau password salah.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_mechanic_can_read_parts_but_cannot_manage_inventory_or_reports(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->getJson('/api/spare-parts')->assertOk();
        $this->postJson('/api/spare-parts', [])->assertForbidden();
        $this->getJson('/api/suppliers')->assertForbidden();
        $this->getJson('/api/reports/stock')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_audit_log_never_contains_password_hashes(): void
    {
        $user = User::factory()->owner()->create();

        $log = AuditLog::query()->where('table_name', 'users')->where('record_id', $user->id)->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
    }
}
