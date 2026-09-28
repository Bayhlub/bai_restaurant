<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $noy;

    private User $kham;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->noy = User::factory()->cashier()->create(['name' => 'Noy']);
        $this->kham = User::factory()->cashier()->create(['name' => 'Kham']);
    }

    /** A paid session whose invoice lands on the given day, with one accepted and one rejected line. */
    private function sale(Carbon $paidAt, User $cashier, int $total, int $discount = 0, string $dish = 'Laap', int $qty = 1): Invoice
    {
        $session = TableSession::factory()->paid()->create();
        $order = Order::factory()->for($session, 'session')->served()->create();
        OrderItem::factory()->for($order)->create(['name_en' => $dish, 'name_lo' => $dish, 'qty' => $qty, 'unit_price' => 1000]);
        OrderItem::factory()->for($order)->rejected()->create(['name_en' => 'Rejected dish', 'qty' => 5]);

        return Invoice::factory()->for($session, 'session')->for($cashier, 'cashier')->create([
            'subtotal' => $total + $discount, 'discount' => $discount, 'total' => $total, 'paid_at' => $paidAt,
        ]);
    }

    public function test_today_summary_only_counts_todays_invoices(): void
    {
        $this->sale(now(), $this->noy, 30000, 5000);
        $this->sale(now(), $this->kham, 10000);
        $this->sale(now()->subDays(3), $this->noy, 99000);

        Volt::test('admin.reports')
            ->assertSet('preset', 'today')
            ->assertSet('summary', ['invoices' => 2, 'revenue' => 40000.0, 'discounts' => 5000.0, 'average' => 20000.0]);
    }

    public function test_presets_change_the_date_range(): void
    {
        $this->sale(now()->subDay(), $this->noy, 7000);

        Volt::test('admin.reports')
            ->set('preset', 'yesterday')
            ->assertSet('from', now()->subDay()->toDateString())
            ->assertSet('to', now()->subDay()->toDateString())
            ->assertSee('7,000')
            ->set('preset', 'month')
            ->assertSet('from', now()->startOfMonth()->toDateString());
    }

    public function test_custom_dates_switch_to_the_custom_preset_and_swap_if_reversed(): void
    {
        $this->sale(now()->subDays(10), $this->noy, 5000);

        Volt::test('admin.reports')
            ->set('from', now()->toDateString())
            ->set('to', now()->subDays(12)->toDateString())
            ->assertSet('preset', 'custom')
            ->assertSee('5,000');
    }

    public function test_top_dishes_exclude_rejected_lines_and_sum_quantities(): void
    {
        $this->sale(now(), $this->noy, 1000, dish: 'Pho', qty: 3);
        $this->sale(now(), $this->noy, 1000, dish: 'Pho', qty: 2);
        $this->sale(now(), $this->noy, 1000, dish: 'Laap', qty: 1);

        $component = Volt::test('admin.reports');
        $top = $component->get('topItems');

        $this->assertSame('Pho', $top[0]->name_en);
        $this->assertEquals(5, $top[0]->qty);
        $this->assertEquals(5000, $top[0]->revenue);
        $this->assertSame('Laap', $top[1]->name_en);
        $this->assertFalse($top->contains('name_en', 'Rejected dish'));

        $component->assertSee(__(':count items rejected', ['count' => 15]));
    }

    public function test_revenue_is_grouped_by_cashier_and_by_day(): void
    {
        $this->sale(now(), $this->noy, 10000);
        $this->sale(now(), $this->noy, 20000);
        $this->sale(now()->subDay(), $this->kham, 5000);

        $component = Volt::test('admin.reports')->set('preset', 'week');

        if (now()->dayOfWeek === Carbon::MONDAY) {
            // Yesterday is outside "this week"; widen the range so both days are included.
            $component->set('from', now()->subDay()->toDateString());
        }

        $byCashier = $component->get('byCashier');
        $this->assertSame('Noy', $byCashier[0]->cashier);
        $this->assertEquals(30000, $byCashier[0]->revenue);
        $this->assertEquals(2, $byCashier[0]->invoices);
        $this->assertSame('Kham', $byCashier[1]->cashier);

        $byDay = $component->get('byDay');
        $this->assertCount(2, $byDay);
        $this->assertEquals(30000, $byDay->last()->revenue);
    }

    public function test_reports_are_admin_only(): void
    {
        $this->actingAs($this->noy)->get('/admin/reports')->assertForbidden();
    }
}
