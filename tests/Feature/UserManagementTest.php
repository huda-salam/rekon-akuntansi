<?php

namespace Tests\Feature;

use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_skpd_user_and_assign_scope(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Operator A',
                'email' => 'operator@example.test',
                'password' => 'password123',
                'role' => 'skpd',
                'skpd_id' => $skpd->id,
            ])
            ->assertCreated()
            ->assertJsonPath('role', 'skpd')
            ->assertJsonPath('skpd_id', $skpd->id);

        $this->assertDatabaseHas('users', [
            'email' => 'operator@example.test',
            'role' => 'skpd',
            'skpd_id' => $skpd->id,
            'is_active' => 1,
        ]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $skpd = Skpd::create(['code' => 'SKPD-A', 'name' => 'SKPD A', 'is_active' => true]);
        $user = User::create([
            'name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'password',
            'role' => 'skpd', 'skpd_id' => $skpd->id, 'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_role_scope_is_validated(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Invalid',
                'email' => 'invalid@example.test',
                'password' => 'password123',
                'role' => 'skpd',
            ])
            ->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::create([
            'name' => 'Inactive', 'email' => 'inactive@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_deactivating_user_revokes_tokens(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $target = User::create([
            'name' => 'Target', 'email' => 'target@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $token = $target->createToken('test')->accessToken;

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$target->id}", ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token]);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => 0]);
    }
}
