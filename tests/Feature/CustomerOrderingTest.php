<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CustomerOrderingTest extends TestCase
{
    use RefreshDatabase;

    private Table $table;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->table = Table::factory()->create(['number' => '1']);
        $this->category = Category::factory()->create();

        // Volt::test() bypasses route middleware, so mirror the customer default set in SetLocale.
        app()->setLocale('lo');
    }

    public function test_menu_page_renders_for_a_valid_table_token(): void
    {
        $item = MenuItem::factory()->for($this->category)->create(['name_lo' => 'ລາບໄກ່', 'name_en' => 'Chicken Laap']);

        $this->get('/t/'.$this->table->token)
            ->assertOk()
            ->assertSeeVolt('customer.table')
            ->assertSee('ລາບໄກ່')
            ->assertSee(number_format($item->price));
    }

    public function test_photos_use_host_relative_urls_so_phones_on_the_lan_can_load_them(): void
    {
        $item = MenuItem::factory()->for($this->category)->create(['image_path' => 'menu/laap.jpg']);

        $this->assertSame('/storage/menu/laap.jpg', $item->imageUrl());

        $this->get('/t/'.$this->table->token)
            ->assertSee('src="/storage/menu/laap.jpg"', false)
            ->assertDontSee('bai_restaurant.test/storage');
    }

    public function test_customers_see_lao_by_default_and_can_switch_to_english(): void
    {
        MenuItem::factory()->for($this->category)->create(['name_lo' => 'ລາບໄກ່', 'name_en' => 'Chicken Laap']);

        $this->get('/t/'.$this->table->token)->assertSee('ລາບໄກ່')->assertDontSee('Chicken Laap');
        $this->get('/t/'.$this->table->token.'?lang=en')->assertSee('Chicken Laap');
    }

    public function test_unknown_or_inactive_table_tokens_are_not_found(): void
    {
        $inactive = Table::factory()->create(['is_active' => false]);

        $this->get('/t/does-not-exist')->assertNotFound();
        $this->get('/t/'.$inactive->token)->assertNotFound();
    }

    public function test_inactive_items_are_hidden_and_sold_out_items_cannot_be_added(): void
    {
        $hidden = MenuItem::factory()->for($this->category)->inactive()->create(['name_lo' => 'ລາຍການລັບ']);
        $soldOut = MenuItem::factory()->for($this->category)->unavailable()->create();

        Volt::test('customer.table', ['token' => $this->table->token])
            ->assertDontSee('ລາຍການລັບ')
            ->call('add', $soldOut->id)
            ->assertSet('cart', [])
            ->assertSet('flashError', __('Sorry, this item is sold out.'));

        $this->assertTrue($hidden->exists);
    }

    public function test_submitting_the_cart_opens_a_session_and_creates_an_order(): void
    {
        $laap = MenuItem::factory()->for($this->category)->create(['price' => 45000]);
        $rice = MenuItem::factory()->for($this->category)->create(['price' => 8000]);

        Volt::test('customer.table', ['token' => $this->table->token])
            ->call('add', $laap->id)
            ->call('add', $laap->id)
            ->call('add', $rice->id)
            ->set('cart.'.$rice->id.'.note', 'extra')
            ->set('orderNote', 'Quickly please')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('cart', [])
            ->assertSet('cartOpen', false)
            ->assertDispatched('order-placed')
            ->assertSee(__('Order #:number sent to the kitchen!', ['number' => 1]));

        $this->table->refresh();
        $session = $this->table->openSession;

        $this->assertSame(TableStatus::Occupied, $this->table->status);
        $this->assertNotNull($session);
        $this->assertSame(SessionStatus::Open, $session->status);

        $order = $session->orders->sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('Quickly please', $order->note);
        $this->assertSame(1, $order->daily_number);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'menu_item_id' => $laap->id, 'qty' => 2, 'unit_price' => 45000, 'status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'menu_item_id' => $rice->id, 'qty' => 1, 'note' => 'extra',
        ]);
        $this->assertEquals(98000, $session->subtotal());
    }

    public function test_a_second_order_joins_the_same_session(): void
    {
        $item = MenuItem::factory()->for($this->category)->create();

        Volt::test('customer.table', ['token' => $this->table->token])
            ->call('add', $item->id)
            ->call('submit')
            ->call('add', $item->id)
            ->call('submit');

        $this->assertSame(1, TableSession::count());
        $this->assertSame(2, Order::count());
        $this->assertSame([1, 2], Order::orderBy('id')->pluck('daily_number')->all());
    }

    public function test_order_items_keep_the_price_at_order_time(): void
    {
        $item = MenuItem::factory()->for($this->category)->create(['price' => 10000]);

        Volt::test('customer.table', ['token' => $this->table->token])
            ->call('add', $item->id)
            ->call('submit');

        $item->update(['price' => 99000]);

        $this->assertEquals(10000, OrderItem::sole()->unit_price);
    }

    public function test_items_that_sell_out_before_submitting_are_dropped_with_a_message(): void
    {
        $ok = MenuItem::factory()->for($this->category)->create();
        $gone = MenuItem::factory()->for($this->category)->create(['name_lo' => 'ປີ້ງປາ']);

        $component = Volt::test('customer.table', ['token' => $this->table->token])
            ->call('add', $ok->id)
            ->call('add', $gone->id);

        $gone->update(['is_available' => false]);

        $component->call('submit')
            ->assertSet('flashError', __('Not available: :items', ['items' => 'ປີ້ງປາ']));

        $this->assertSame([$ok->id], OrderItem::pluck('menu_item_id')->all());
    }

    public function test_submitting_when_everything_sold_out_creates_nothing(): void
    {
        $gone = MenuItem::factory()->for($this->category)->create();

        $component = Volt::test('customer.table', ['token' => $this->table->token])->call('add', $gone->id);

        $gone->update(['is_available' => false]);

        $component->call('submit')
            ->assertSet('cart', [])
            ->assertSet('flashError', __('Sorry, everything in your cart is sold out.'));

        $this->assertSame(0, Order::count());
        $this->assertSame(0, TableSession::count());
    }

    public function test_submitting_an_empty_cart_does_nothing(): void
    {
        Volt::test('customer.table', ['token' => $this->table->token])
            ->call('submit')
            ->assertSet('flashError', __('Your cart is empty.'));

        $this->assertSame(0, Order::count());
    }

    public function test_decrementing_to_zero_removes_the_line_and_closes_the_cart(): void
    {
        $item = MenuItem::factory()->for($this->category)->create();

        Volt::test('customer.table', ['token' => $this->table->token])
            ->call('add', $item->id)
            ->call('toggleCart')
            ->assertSet('cartOpen', true)
            ->call('decrement', $item->id)
            ->assertSet('cart', [])
            ->assertSet('cartOpen', false);
    }

    public function test_customer_sees_their_orders_with_rejected_items_marked(): void
    {
        $session = TableSession::factory()->for($this->table)->create();
        $order = Order::factory()->for($session, 'session')->create(['status' => OrderStatus::Cooking]);
        OrderItem::factory()->for($order)->create(['name_lo' => 'ລາບໄກ່', 'status' => OrderItemStatus::Accepted, 'unit_price' => 45000, 'qty' => 1]);
        OrderItem::factory()->for($order)->rejected()->create(['name_lo' => 'ປີ້ງປາ', 'rejection_reason' => 'ໝົດ', 'unit_price' => 70000, 'qty' => 1]);

        Volt::test('customer.table', ['token' => $this->table->token])
            ->assertSee('ລາບໄກ່')
            ->assertSee('ປີ້ງປາ')
            ->assertSee('ໝົດ')
            ->assertSee(__('orders.status.cooking'))
            ->assertSee(__('orders.item_status.rejected'))
            ->assertSeeInOrder([__('Total so far'), '45,000']);
    }

    public function test_paid_sessions_are_not_shown_to_the_next_customer(): void
    {
        $session = TableSession::factory()->for($this->table)->paid()->create();
        $order = Order::factory()->for($session, 'session')->served()->create();
        OrderItem::factory()->for($order)->create(['name_lo' => 'ອໍເດີເກົ່າ']);

        Volt::test('customer.table', ['token' => $this->table->token])
            ->assertDontSee('ອໍເດີເກົ່າ')
            ->assertSee(__('Nothing ordered yet. Pick something from the menu above!'));
    }
}
