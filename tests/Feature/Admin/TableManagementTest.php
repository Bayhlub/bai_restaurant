<?php

namespace Tests\Feature\Admin;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_create_a_table_with_a_qr_token(): void
    {
        Volt::test('admin.tables')
            ->call('newTable')
            ->set('number', 'VIP-1')
            ->set('seats', 8)
            ->call('saveTable')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $table = Table::where('number', 'VIP-1')->first();

        $this->assertNotNull($table);
        $this->assertSame(8, $table->seats);
        $this->assertSame(32, strlen($table->token));
    }

    public function test_table_number_must_be_unique(): void
    {
        Table::factory()->create(['number' => '7']);

        Volt::test('admin.tables')
            ->call('newTable')
            ->set('number', '7')
            ->call('saveTable')
            ->assertHasErrors('number');
    }

    public function test_editing_a_table_keeps_its_own_number_valid(): void
    {
        $table = Table::factory()->create(['number' => '7', 'seats' => 4]);

        Volt::test('admin.tables')
            ->call('editTable', $table->id)
            ->set('seats', 6)
            ->call('saveTable')
            ->assertHasNoErrors();

        $this->assertSame(6, $table->fresh()->seats);
    }

    public function test_regenerating_token_changes_the_customer_url(): void
    {
        $table = Table::factory()->create();
        $oldToken = $table->token;

        Volt::test('admin.tables')->call('regenerateToken', $table->id);

        $this->assertNotSame($oldToken, $table->fresh()->token);
        $this->get('/t/'.$oldToken)->assertNotFound();
        $this->get('/t/'.$table->fresh()->token)->assertOk();
    }

    public function test_table_with_history_cannot_be_deleted(): void
    {
        $table = Table::factory()->create();
        TableSession::factory()->for($table)->create();

        Volt::test('admin.tables')
            ->call('deleteTable', $table->id)
            ->assertHasErrors('table');

        $this->assertModelExists($table);
    }

    public function test_unused_table_can_be_deleted(): void
    {
        $table = Table::factory()->create();

        Volt::test('admin.tables')->call('deleteTable', $table->id);

        $this->assertModelMissing($table);
    }

    public function test_status_column_follows_sessions_when_the_page_polls(): void
    {
        $table = Table::factory()->create(['number' => '5']);

        $component = Volt::test('admin.tables')->assertSee(__('Free'));

        // A customer orders: the table becomes occupied without the admin reloading.
        $session = $table->openOrStartSession();

        $component->call('refresh')
            ->assertSee(__('Occupied'))
            ->assertSee(__('since').' '.$session->opened_at->format('H:i'));

        $table->requestService();
        $session->requestBill();

        $component->call('refresh')->assertSee('🔔')->assertSee('💵');

        // The cashier settles the bill: back to free.
        $session->update(['status' => SessionStatus::Paid, 'closed_at' => now()]);
        $table->update(['status' => TableStatus::Free]);

        $component->call('refresh')->assertSee(__('Free'))->assertDontSee(__('Occupied'));
    }

    public function test_polling_does_not_disturb_a_half_filled_form(): void
    {
        Volt::test('admin.tables')
            ->call('newTable')
            ->set('number', 'VIP-9')
            ->set('seats', 8)
            ->call('refresh')
            ->assertSet('number', 'VIP-9')
            ->assertSet('seats', 8)
            ->call('saveTable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tables', ['number' => 'VIP-9', 'seats' => 8]);
    }

    public function test_qr_url_uses_the_configured_customer_base_url(): void
    {
        config(['restaurant.customer_url' => 'http://192.168.0.168:8000/']);
        $table = Table::factory()->create(['token' => 'abc123']);

        $this->assertSame('http://192.168.0.168:8000/t/abc123', $table->customerUrl());
        $this->get('/admin/tables/qr')->assertSee('192.168.0.168:8000/t/abc123');
    }

    public function test_qr_print_sheet_lists_active_tables_only(): void
    {
        Table::factory()->create(['number' => '1']);
        Table::factory()->create(['number' => '2', 'is_active' => false]);

        $this->get('/admin/tables/qr')
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSeeText('1')
            ->assertDontSeeText('Table 2');
    }
}
