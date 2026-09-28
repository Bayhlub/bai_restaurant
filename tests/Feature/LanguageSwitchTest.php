<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_language_stores_it_and_returns_to_the_previous_page(): void
    {
        $table = Table::factory()->create();
        $page = '/t/'.$table->token;

        $this->from($page)->get('/lang/en')->assertRedirect($page);

        $this->assertSame('en', session('locale'));
        $this->get($page)->assertSee('Your orders')->assertDontSee('ລາຍການທີ່ສັ່ງແລ້ວ');

        $this->from($page)->get('/lang/lo')->assertRedirect($page);
        $this->get($page)->assertSee('ລາຍການທີ່ສັ່ງແລ້ວ');
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->get('/lang/fr')->assertNotFound();
        $this->assertNull(session('locale'));
    }

    public function test_language_links_stay_valid_after_a_livewire_rerender(): void
    {
        $table = Table::factory()->create();

        // A poll re-renders the page inside POST /livewire/update; the links must not point there.
        Volt::test('customer.table', ['token' => $table->token])
            ->call('$refresh')
            ->assertSee('href="/lang/en"', false)
            ->assertDontSee('livewire/update?lang');
    }

    public function test_staff_navigation_also_uses_the_switch_route(): void
    {
        $this->actingAs(User::factory()->kitchen()->create())
            ->get('/kitchen')
            ->assertSee('href="/lang/lo"', false);
    }
}
