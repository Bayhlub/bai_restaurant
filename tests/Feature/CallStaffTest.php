<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CallStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_call_staff_from_the_table_page(): void
    {
        $table = Table::factory()->create();

        Volt::test('customer.table', ['token' => $table->token])
            ->assertSee(__('Call staff'))
            ->call('callStaff')
            ->assertSee(__('Called ✓'))
            ->assertSet('flash', __('Staff have been called and will be with you shortly.'));

        $this->assertNotNull($table->fresh()->service_requested_at);
    }

    public function test_cashier_sees_the_call_and_can_clear_it(): void
    {
        $this->actingAs(User::factory()->cashier()->create());
        $table = Table::factory()->create(['number' => '7']);
        $table->requestService();

        Volt::test('cashier.tables')
            ->assertSee('🔔')
            ->assertSee(__('Table').' 7')
            ->call('clearServiceRequest', $table->id)
            ->assertDontSee('🔔');

        $this->assertNull($table->fresh()->service_requested_at);
    }
}
