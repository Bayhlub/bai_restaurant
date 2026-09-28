<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function roleRouteMatrix(): array
    {
        return [
            'kitchen sees kitchen' => ['kitchen', '/kitchen', 200],
            'kitchen blocked from cashier' => ['kitchen', '/cashier', 403],
            'kitchen blocked from admin' => ['kitchen', '/admin/menu', 403],
            'cashier sees cashier' => ['cashier', '/cashier', 200],
            'cashier blocked from kitchen' => ['cashier', '/kitchen', 403],
            'cashier blocked from admin' => ['cashier', '/admin/tables', 403],
            'admin sees kitchen' => ['admin', '/kitchen', 200],
            'admin sees cashier' => ['admin', '/cashier', 200],
            'admin sees admin' => ['admin', '/admin/menu', 200],
        ];
    }

    #[DataProvider('roleRouteMatrix')]
    public function test_routes_are_restricted_by_role(string $role, string $path, int $expectedStatus): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->get($path)->assertStatus($expectedStatus);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/kitchen')->assertRedirect('/login');
        $this->get('/admin/menu')->assertRedirect('/login');
    }

    public function test_home_redirects_each_role_to_its_screen(): void
    {
        $this->actingAs(User::factory()->kitchen()->create())->get('/')->assertRedirect('/kitchen');
        $this->actingAs(User::factory()->cashier()->create())->get('/')->assertRedirect('/cashier');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertRedirect('/admin/menu');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
