<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_uses_a_different_hashed_password_without_environment(): void
    {
        config(['pharma.demo_password' => 'unused-old-password']);
        $this->assertCount(4, array_unique(DatabaseSeeder::DEMO_PASSWORDS));
        $users = app(DatabaseSeeder::class)->seedDemoUsers();
        foreach (DatabaseSeeder::DEMO_USERS as [$role, $name, $email]) {
            $user = $users[$email];
            $password = DatabaseSeeder::DEMO_PASSWORDS[$role];
            $this->assertNotSame($password, $user->getRawOriginal('password'));
            $this->assertTrue(Hash::check($password, $user->password));
            $this->postJson('/api/login', ['email' => $email, 'password' => $password])->assertOk()->assertJsonPath('user.role', $role);
            $this->postJson('/api/logout')->assertOk();
        }
    }

    public function test_reseeding_updates_demo_only_and_preserves_contacts_and_password_hash_on_retry(): void
    {
        $real = User::factory()->create(['email' => 'staff@example.test', 'role' => 'admin']);
        $realHash = $real->password;
        $demo = User::factory()->create(['email' => 'admin@pharma.test', 'role' => 'admin', 'name' => 'Name to keep', 'password' => 'old-demo-password', 'telegram_chat_id' => '12345']);
        $seeder = app(DatabaseSeeder::class);
        $seeder->seedDemoUsers();
        $demo->refresh();
        $this->assertTrue(Hash::check(DatabaseSeeder::DEMO_PASSWORDS['admin'], $demo->password));
        $this->assertSame('Name to keep', $demo->name);
        $this->assertSame('12345', $demo->telegram_chat_id);
        $hash = $demo->password;
        $seeder->seedDemoUsers();
        $this->assertSame($hash, $demo->fresh()->password);
        $this->assertSame($realHash, $real->fresh()->password);
        $this->assertDatabaseCount('users', count(DatabaseSeeder::DEMO_USERS) + 1);
    }
}
