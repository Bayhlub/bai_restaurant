<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CashierTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->cashier()->create(['name' => 'Noy']);
        $this->actingAs($this->cashier);
    }

    /** A table with one served order: 2 × 10,000 accepted and 1 × 50,000 rejected. */
    private function occupiedTable(string $number = '3'): TableSession
    {
        $table = Table::factory()->occupied()->create(['number' => $number]);
        $session = TableSession::factory()->for($table)->create();
        $order = Order::factory()->for($session, 'session')->served()->create();
        OrderItem::factory()->for($order)->create(['name_lo' => 'ລາບໄກ່', 'name_en' => 'Chicken Laap', 'unit_price' => 10000, 'qty' => 2]);
        OrderItem::factory()->for($order)->rejected()->create(['name_lo' => 'ປີ້ງປາ', 'unit_price' => 50000, 'qty' => 1]);

        return $session;
    }

    public function test_table_grid_shows_occupied_tables_with_running_totals(): void
    {
        $this->occupiedTable('3');
        Table::factory()->create(['number' => '4']);
        Table::factory()->create(['number' => 'OLD', 'is_active' => false]);

        Volt::test('cashier.tables')
            ->assertSeeInOrder(['3', '20,000', __(':count orders', ['count' => 1]), '4', __('Free')])
            ->assertDontSee('OLD');
    }

    public function test_table_card_flags_orders_still_in_the_kitchen(): void
    {
        $session = $this->occupiedTable();
        Order::factory()->for($session, 'session')->create(['status' => OrderStatus::Cooking]);
        Order::factory()->for($session, 'session')->ready()->create();

        Volt::test('cashier.tables')
            ->assertSee('1 '.__('in kitchen'))
            ->assertSee('1 '.__('ready'));
    }

    public function test_session_page_lists_items_and_bill_totals(): void
    {
        $session = $this->occupiedTable();

        $this->get(route('cashier.session', $session))
            ->assertOk()
            ->assertSeeVolt('cashier.session')
            ->assertSee('ລາບໄກ່')
            ->assertSee('ປີ້ງປາ')
            ->assertSeeInOrder([__('Subtotal'), '20,000']);
    }

    public function test_change_is_calculated_from_discount_and_cash(): void
    {
        $session = $this->occupiedTable();

        Volt::test('cashier.session', ['session' => $session])
            ->set('discount', '5000')
            ->set('cashReceived', '50000')
            ->assertSet('total', 15000.0)
            ->assertSet('change', 35000.0)
            ->call('exactCash')
            ->assertSet('cashReceived', '15000')
            ->assertSet('change', 0.0);
    }

    public function test_closing_the_bill_creates_an_invoice_and_frees_the_table(): void
    {
        $session = $this->occupiedTable();

        Volt::test('cashier.session', ['session' => $session])
            ->set('discount', '5000')
            ->set('cashReceived', '50000')
            ->call('closeBill')
            ->assertHasNoErrors()
            ->assertRedirect(route('invoice.print', Invoice::first()));

        $invoice = Invoice::sole();

        $this->assertSame('INV-'.now()->format('Ymd').'-0001', $invoice->number);
        $this->assertSame($this->cashier->id, $invoice->cashier_id);
        $this->assertEquals(20000, $invoice->subtotal);
        $this->assertEquals(5000, $invoice->discount);
        $this->assertEquals(15000, $invoice->total);
        $this->assertEquals(50000, $invoice->cash_received);
        $this->assertEquals(35000, $invoice->change_amount);
        $this->assertNotNull($invoice->paid_at);

        $session->refresh();
        $this->assertSame(SessionStatus::Paid, $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertSame(TableStatus::Free, $session->table->status);
    }

    public function test_invoice_numbers_increment_per_day(): void
    {
        Invoice::factory()->create(['number' => 'INV-'.now()->format('Ymd').'-0007']);

        $this->assertSame('INV-'.now()->format('Ymd').'-0008', Invoice::nextNumber());
    }

    public function test_bill_cannot_be_closed_with_insufficient_cash(): void
    {
        $session = $this->occupiedTable();

        Volt::test('cashier.session', ['session' => $session])
            ->set('cashReceived', '10000')
            ->call('closeBill')
            ->assertSet('error', __('Cash received is less than the total.'))
            ->assertNoRedirect();

        $this->assertSame(0, Invoice::count());
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
    }

    public function test_discount_cannot_exceed_the_subtotal(): void
    {
        $session = $this->occupiedTable();

        Volt::test('cashier.session', ['session' => $session])
            ->set('discount', '25000')
            ->set('cashReceived', '100000')
            ->call('closeBill')
            ->assertSet('error', __('Discount cannot exceed the subtotal.'));

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_paid_session_cannot_be_closed_twice(): void
    {
        $session = $this->occupiedTable();
        Invoice::factory()->for($session, 'session')->create();
        $session->update(['status' => SessionStatus::Paid]);

        Volt::test('cashier.session', ['session' => $session])
            ->assertSee(__('Paid'))
            ->assertDontSee(__('Pay & print invoice'))
            ->set('cashReceived', '100000')
            ->call('closeBill')
            ->assertSet('error', __('This bill has already been paid.'));

        $this->assertSame(1, Invoice::count());
    }

    public function test_receipt_prints_billable_lines_and_totals_and_stamps_printed_at(): void
    {
        $session = $this->occupiedTable();
        $invoice = Invoice::factory()->for($session, 'session')->for($this->cashier, 'cashier')->create([
            'subtotal' => 20000, 'discount' => 5000, 'total' => 15000, 'cash_received' => 50000, 'change_amount' => 35000,
        ]);

        $this->get(route('invoice.print', $invoice).'?noprint=1')
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('ລາບໄກ່')
            ->assertDontSee('ປີ້ງປາ')
            ->assertSee('Noy')
            ->assertSeeInOrder(['20,000', '-5,000', '15,000', '50,000', '35,000'])
            ->assertDontSee("addEventListener('load'", false);

        $this->assertNotNull($invoice->fresh()->printed_at);

        $this->get(route('invoice.print', $invoice))->assertSee("addEventListener('load'", false);
    }

    public function test_kitchen_staff_cannot_open_the_cashier_screens(): void
    {
        $session = $this->occupiedTable();
        $invoice = Invoice::factory()->for($session, 'session')->create();

        $this->actingAs(User::factory()->kitchen()->create());

        $this->get(route('cashier.session', $session))->assertForbidden();
        $this->get(route('invoice.print', $invoice))->assertForbidden();
    }
}
