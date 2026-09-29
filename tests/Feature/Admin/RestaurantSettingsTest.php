<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RestaurantSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_form_shows_the_details_currently_in_use(): void
    {
        config(['app.name' => 'Bai Restaurant', 'restaurant.address' => 'Vientiane, Laos']);

        Volt::test('admin.settings')
            ->assertSet('nameEn', 'Bai Restaurant')
            ->assertSet('address', 'Vientiane, Laos');
    }

    public function test_admin_can_change_the_restaurant_details(): void
    {
        Volt::test('admin.settings')
            ->set('nameLo', 'ຮ້ານອາຫານ ສີດາ')
            ->set('nameEn', 'Sida Restaurant')
            ->set('address', 'Luang Prabang')
            ->set('phone', '020 5555 1234')
            ->set('footerLo', 'ຂອບໃຈ')
            ->set('footerEn', 'Thank you')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $this->assertSame('Sida Restaurant', Setting::where('key', 'name_en')->value('value'));
        $this->assertSame('Luang Prabang', Setting::where('key', 'address')->value('value'));

        // Stored values take over from the .env defaults.
        $this->assertSame('Sida Restaurant', Settings::get('name_en'));
        Settings::applyToConfig();
        $this->assertSame('Sida Restaurant', config('app.name'));
        $this->assertSame('Luang Prabang', config('restaurant.address'));
    }

    public function test_both_names_are_required(): void
    {
        Volt::test('admin.settings')
            ->set('nameLo', '')
            ->set('nameEn', '')
            ->call('save')
            ->assertHasErrors(['nameLo', 'nameEn'])
            ->assertSet('saved', false);

        $this->assertSame(0, Setting::count());
    }

    public function test_a_blank_optional_field_falls_back_to_the_env_default(): void
    {
        config(['restaurant.phone' => '021 000 000']);

        Volt::test('admin.settings')
            ->set('phone', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Setting::where('key', 'phone')->value('value'));

        // Nothing stored means config keeps whatever .env provided.
        config(['restaurant.phone' => '021 000 000']);
        Settings::applyToConfig();
        $this->assertSame('021 000 000', config('restaurant.phone'));
    }

    public function test_new_details_reach_the_customer_page_and_the_receipt(): void
    {
        Settings::save([
            'name_lo' => 'ຮ້ານອາຫານ ສີດາ',
            'name_en' => 'Sida Restaurant',
            'address' => 'Luang Prabang',
            'footer_en' => 'See you again',
        ]);
        Settings::applyToConfig();

        $table = Table::factory()->create();
        $this->get('/t/'.$table->token.'?lang=en')->assertSee('Sida Restaurant');

        $session = TableSession::factory()->paid()->create();
        $order = Order::factory()->for($session, 'session')->served()->create();
        OrderItem::factory()->for($order)->create();
        $invoice = Invoice::factory()->for($session, 'session')->create();

        $this->get(route('invoice.print', $invoice).'?noprint=1')
            ->assertSee('ຮ້ານອາຫານ ສີດາ')
            ->assertSee('Sida Restaurant')
            ->assertSee('Luang Prabang')
            ->assertSee('See you again');

        $this->get('/admin/tables/qr')->assertSee('Sida Restaurant');
    }

    public function test_the_page_reports_when_no_backup_has_been_taken(): void
    {
        config(['backup.path' => storage_path('framework/testing/empty-backups')]);

        Volt::test('admin.settings')->assertSee(__('No backup has been taken yet.'));
    }

    public function test_the_page_warns_when_the_newest_backup_is_stale(): void
    {
        $dir = storage_path('framework/testing/stale-backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/backup-2026-01-01_000000.sqlite';
        File::put($file, 'x');
        touch($file, now()->subDays(5)->getTimestamp());
        config(['backup.path' => $dir]);

        try {
            Volt::test('admin.settings')
                ->assertSee(__('Last backup'))
                ->assertSee(__('That is more than two days ago — check that the scheduled task is still running.'));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_settings_are_admin_only(): void
    {
        $this->actingAs(User::factory()->cashier()->create())
            ->get('/admin/settings')
            ->assertForbidden();
    }
}
