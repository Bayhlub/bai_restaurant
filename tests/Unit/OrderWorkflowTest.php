<?php

namespace Tests\Unit;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_cooking_accepts_pending_items_and_moves_the_order_on(): void
    {
        $order = Order::factory()->create();
        $pending = OrderItem::factory()->for($order)->create();
        $rejected = OrderItem::factory()->for($order)->rejected()->create();

        $order->startCooking();

        $this->assertSame(OrderStatus::Cooking, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cooking_at);
        $this->assertNotNull($order->fresh()->accepted_at);
        $this->assertSame(OrderItemStatus::Accepted, $pending->fresh()->status);
        $this->assertSame(OrderItemStatus::Rejected, $rejected->fresh()->status);
    }

    public function test_start_cooking_cancels_an_order_with_every_item_rejected(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->rejected()->count(2)->create();

        $order->startCooking();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNull($order->fresh()->cooking_at);
    }

    public function test_ready_and_served_update_order_and_non_rejected_items(): void
    {
        $order = Order::factory()->create();
        $item = OrderItem::factory()->for($order)->create();
        $rejected = OrderItem::factory()->for($order)->rejected()->create();
        $order->startCooking();

        $order->markReady();
        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        $this->assertSame(OrderItemStatus::Ready, $item->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_at);

        $order->markServed();
        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
        $this->assertSame(OrderItemStatus::Served, $item->fresh()->status);
        $this->assertSame(OrderItemStatus::Rejected, $rejected->fresh()->status);
        $this->assertNotNull($order->fresh()->served_at);
    }

    public function test_reject_and_unreject_toggle_an_item(): void
    {
        $item = OrderItem::factory()->create();

        $item->reject('Sold out');
        $this->assertSame(OrderItemStatus::Rejected, $item->fresh()->status);
        $this->assertSame('Sold out', $item->fresh()->rejection_reason);
        $this->assertSame(OrderStatus::Pending, $item->order->fresh()->status);

        $item->unreject();
        $this->assertSame(OrderItemStatus::Pending, $item->fresh()->status);
        $this->assertNull($item->fresh()->rejection_reason);
    }

    public function test_order_total_excludes_rejected_items(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['unit_price' => 10000, 'qty' => 2]);
        OrderItem::factory()->for($order)->rejected()->create(['unit_price' => 50000, 'qty' => 1]);

        $this->assertEquals(20000, $order->fresh()->total());
        $this->assertTrue($order->fresh()->hasBillableItems());
    }
}
