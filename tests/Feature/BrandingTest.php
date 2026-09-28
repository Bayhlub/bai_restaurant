<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.name' => 'Bai Restaurant', 'restaurant.name_lo' => 'ຮ້ານອາຫານ ໄບ']);
    }

    public function test_login_page_shows_both_names_with_the_current_language_first(): void
    {
        $this->get('/login?lang=lo')->assertSeeInOrder(['ຮ້ານອາຫານ ໄບ', 'Bai Restaurant']);
        $this->get('/login?lang=en')->assertSeeInOrder(['Bai Restaurant', 'ຮ້ານອາຫານ ໄບ']);
    }

    public function test_customer_page_shows_the_name_in_the_customers_language(): void
    {
        $table = Table::factory()->create();

        $this->get('/t/'.$table->token)->assertSee('ຮ້ານອາຫານ ໄບ');
        $this->get('/t/'.$table->token.'?lang=en')->assertSee('Bai Restaurant');
    }

    public function test_qr_sheet_and_receipt_carry_both_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Table::factory()->create(['number' => '1']);

        $this->get('/admin/tables/qr')->assertSeeInOrder(['ຮ້ານອາຫານ ໄບ', 'Bai Restaurant']);

        $session = TableSession::factory()->paid()->create();
        $order = Order::factory()->for($session, 'session')->served()->create();
        OrderItem::factory()->for($order)->create();
        $invoice = Invoice::factory()->for($session, 'session')->create();

        $this->get(route('invoice.print', $invoice).'?noprint=1')
            ->assertSeeInOrder(['ຮ້ານອາຫານ ໄບ', 'Bai Restaurant']);
    }

    public function test_logo_files_are_served(): void
    {
        $this->assertFileExists(public_path('logo.svg'));
        $this->assertFileExists(public_path('favicon.svg'));
    }
}
