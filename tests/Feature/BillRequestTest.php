<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BillRequestTest extends TestCase
{
    use RefreshDatabase;

    private function tableWithOrder(string $number = '1'): Table
    {
        $table = Table::factory()->occupied()->create(['number' => $number]);
        $session = TableSession::factory()->for($table)->create();
        $order = Order::factory()->for($session, 'session')->served()->create();
        OrderItem::factory()->for($order)->create(['unit_price' => 10000, 'qty' => 2]);

        return $table;
    }

    public function test_customer_with_orders_can_request_the_bill(): void
    {
        $table = $this->tableWithOrder();

        Volt::test('customer.table', ['token' => $table->token])
            ->assertSee(__('Request bill'))
            ->call('requestBill')
            ->assertSet('flash', __('Bill requested. The cashier will bring your invoice.'))
            ->assertDontSee(__('Request bill'))
            ->assertSee(__('Bill requested. The cashier will bring your invoice.'));

        $this->assertNotNull($table->openSession->fresh()->bill_requested_at);
    }

    public function test_customer_without_orders_has_no_bill_to_request(): void
    {
        $table = Table::factory()->create();

        Volt::test('customer.table', ['token' => $table->token])
            ->assertDontSee(__('Request bill'))
            ->call('requestBill');

        $this->assertNull($table->openSession);
    }

    public function test_requesting_twice_keeps_the_original_time(): void
    {
        $table = $this->tableWithOrder();
        $session = $table->openSession;

        $session->requestBill();
        $first = $session->fresh()->bill_requested_at;

        $this->travel(5)->minutes();
        $session->fresh()->requestBill();

        $this->assertEquals($first, $session->fresh()->bill_requested_at);
    }

    public function test_cashier_is_alerted_once_and_sees_which_table_wants_the_bill(): void
    {
        $this->actingAs(User::factory()->cashier()->create());
        $table = $this->tableWithOrder('7');

        $component = Volt::test('cashier.tables')
            ->assertDontSee(__('Bill requested'))
            ->call('refresh')
            ->assertNotDispatched('cashier-bill-requested');

        $table->openSession->requestBill();

        $component->call('refresh')
            ->assertDispatched('cashier-bill-requested')
            ->assertSee(__('Bill requested'))
            ->assertSee(__('Table').' 7')
            ->assertSee('💵')
            ->assertSee(route('cashier.session', $table->openSession))
            ->call('refresh')
            ->assertNotDispatched('cashier-bill-requested');
    }

    public function test_bill_page_shows_the_request_and_paying_clears_it_from_the_grid(): void
    {
        $this->actingAs(User::factory()->cashier()->create());
        $table = $this->tableWithOrder('7');
        $session = $table->openSession;
        $session->requestBill();

        $this->get(route('cashier.session', $session))
            ->assertSee(__('Customer requested the bill at :time', ['time' => $session->fresh()->bill_requested_at->format('H:i')]));

        Volt::test('cashier.session', ['session' => $session])
            ->set('cashReceived', '20000')
            ->call('closeBill')
            ->assertHasNoErrors();

        $this->assertFalse($session->fresh()->billRequested());

        Volt::test('cashier.tables')->assertDontSee(__('Bill requested'));
        Volt::test('customer.table', ['token' => $table->token])->assertDontSee(__('Bill requested. The cashier will bring your invoice.'));
    }
}
