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

        // Asserts on the banner wording rather than the bell emoji, which also
        // appears in the alerts toggle button.
        Volt::test('cashier.tables')
            ->assertSee(__('Staff called'))
            ->assertSee(__('Table').' 7')
            ->call('clearServiceRequest', $table->id)
            ->assertDontSee(__('Staff called'));

        $this->assertNull($table->fresh()->service_requested_at);
    }

    public function test_cashier_is_alerted_once_when_a_table_calls(): void
    {
        $this->actingAs(User::factory()->cashier()->create());
        $table = Table::factory()->create(['number' => '7']);

        $component = Volt::test('cashier.tables')
            ->call('refresh')
            ->assertNotDispatched('staff-alert');

        $table->requestService();

        $component->call('refresh')
            ->assertDispatched('staff-alert', title: __('Staff called'), body: __('Table').' 7', tag: 'cashier-staff-called')
            ->call('refresh')
            ->assertNotDispatched('staff-alert');
    }

    public function test_clearing_a_call_lets_the_same_table_alert_again(): void
    {
        $this->actingAs(User::factory()->cashier()->create());
        $table = Table::factory()->create(['number' => '7']);
        $table->requestService();

        $component = Volt::test('cashier.tables')
            ->call('clearServiceRequest', $table->id)
            ->call('refresh')
            ->assertNotDispatched('staff-alert')
            ->assertSet('knownServiceCallIds', []);

        // Reload first: the cashier cleared the request, so this instance is stale.
        $this->travel(1)->minute();
        $table->fresh()->requestService();

        $component->call('refresh')
            ->assertDispatched('staff-alert', title: __('Staff called'), body: __('Table').' 7', tag: 'cashier-staff-called');
    }
}
