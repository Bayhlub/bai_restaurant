<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class KitchenBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->kitchen()->create());
    }

    private function orderForTable(string $number, OrderStatus $status = OrderStatus::Pending): Order
    {
        $session = TableSession::factory()->for(Table::factory()->create(['number' => $number]))->create();

        return Order::factory()->for($session, 'session')->create(['status' => $status]);
    }

    public function test_board_groups_active_orders_by_status_and_hides_finished_ones(): void
    {
        OrderItem::factory()->for($this->orderForTable('11'))->create(['name_lo' => 'ໃໝ່ໜຶ່ງ']);
        OrderItem::factory()->for($this->orderForTable('12', OrderStatus::Cooking))->create(['name_lo' => 'ກຳລັງແຕ່ງໜຶ່ງ']);
        OrderItem::factory()->for($this->orderForTable('13', OrderStatus::Ready))->create(['name_lo' => 'ພ້ອມໜຶ່ງ']);
        OrderItem::factory()->for($this->orderForTable('14', OrderStatus::Served))->create(['name_lo' => 'ເສີບແລ້ວໜຶ່ງ']);
        OrderItem::factory()->for($this->orderForTable('15', OrderStatus::Cancelled))->create(['name_lo' => 'ຍົກເລີກໜຶ່ງ']);

        Volt::test('kitchen.board')
            ->assertSeeInOrder(['Table 11', 'ໃໝ່ໜຶ່ງ', 'Table 12', 'ກຳລັງແຕ່ງໜຶ່ງ', 'Table 13', 'ພ້ອມໜຶ່ງ'])
            ->assertDontSee('Table 14')
            ->assertDontSee('Table 15');
    }

    public function test_rejecting_an_item_marks_it_sold_out_on_the_menu(): void
    {
        $menuItem = MenuItem::factory()->create(['name_en' => 'Pork Laap', 'is_available' => true]);
        $line = OrderItem::factory()->for($this->orderForTable('1'))->forMenuItem($menuItem)->create();

        Volt::test('kitchen.board')
            ->call('rejectItem', $line->id)
            ->assertSee(__(':item marked sold out. Re-enable it under Availability.', ['item' => 'Pork Laap']));

        $this->assertSame(OrderItemStatus::Rejected, $line->fresh()->status);
        $this->assertSame(__('Sold out'), $line->fresh()->rejection_reason);
        $this->assertFalse($menuItem->fresh()->is_available);
        $this->assertSame(OrderStatus::Pending, $line->order->fresh()->status);
    }

    public function test_rejected_items_can_be_undone_while_the_ticket_is_new(): void
    {
        $line = OrderItem::factory()->for($this->orderForTable('1'))->rejected()->create();

        Volt::test('kitchen.board')->call('unrejectItem', $line->id);

        $this->assertSame(OrderItemStatus::Pending, $line->fresh()->status);
    }

    public function test_items_cannot_be_rejected_once_cooking_started(): void
    {
        $line = OrderItem::factory()->for($this->orderForTable('1', OrderStatus::Cooking))->create();

        Volt::test('kitchen.board')->call('rejectItem', $line->id);

        $this->assertSame(OrderItemStatus::Pending, $line->fresh()->status);
    }

    public function test_start_cooking_moves_the_ticket_to_the_cooking_column(): void
    {
        $order = $this->orderForTable('1');
        OrderItem::factory()->for($order)->create();

        Volt::test('kitchen.board')
            ->call('startCooking', $order->id)
            ->assertSee(__('Ready'));

        $this->assertSame(OrderStatus::Cooking, $order->fresh()->status);
    }

    public function test_a_fully_rejected_ticket_shows_cancel_and_cancels(): void
    {
        $order = $this->orderForTable('1');
        OrderItem::factory()->for($order)->rejected()->create();

        Volt::test('kitchen.board')
            ->assertSee(__('Cancel order'))
            ->assertDontSee(__('Start cooking'))
            ->call('startCooking', $order->id);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_ready_and_served_transitions(): void
    {
        $order = $this->orderForTable('1', OrderStatus::Cooking);
        OrderItem::factory()->for($order)->create();

        $component = Volt::test('kitchen.board')->call('markReady', $order->id);
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);

        $component->call('markServed', $order->id)->assertDontSee('Table 1');
        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    public function test_refresh_alerts_only_for_tickets_not_seen_before(): void
    {
        OrderItem::factory()->for($this->orderForTable('1'))->create();

        $component = Volt::test('kitchen.board')
            ->call('refresh')
            ->assertNotDispatched('staff-alert');

        OrderItem::factory()->for($this->orderForTable('2'))->create();

        $component->call('refresh')
            ->assertDispatched('staff-alert', title: 'New order', body: 'Table 2', tag: 'kitchen-new-order')
            ->assertSee('Table 2')
            ->call('refresh')
            ->assertNotDispatched('staff-alert');
    }

    public function test_the_alert_names_every_table_when_several_tickets_arrive_at_once(): void
    {
        $component = Volt::test('kitchen.board')->call('refresh');

        OrderItem::factory()->for($this->orderForTable('4'))->create();
        OrderItem::factory()->for($this->orderForTable('5'))->create();

        $component->call('refresh')
            ->assertDispatched('staff-alert', title: '2 new orders', body: 'Table 4, Table 5');
    }

    public function test_the_board_offers_an_alert_toggle(): void
    {
        Volt::test('kitchen.board')->assertSee(__('Alerts off'));
    }

    public function test_kitchen_can_toggle_menu_availability(): void
    {
        $item = MenuItem::factory()->create(['is_available' => true]);

        Volt::test('kitchen.board')->call('toggleAvailability', $item->id);
        $this->assertFalse($item->fresh()->is_available);

        Volt::test('kitchen.board')->call('toggleAvailability', $item->id);
        $this->assertTrue($item->fresh()->is_available);
    }
}
