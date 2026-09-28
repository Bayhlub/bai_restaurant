<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_create_a_category(): void
    {
        Volt::test('admin.menu')
            ->call('newCategory')
            ->set('categoryNameLo', 'ຂອງຫວານ')
            ->set('categoryNameEn', 'Desserts')
            ->set('categorySortOrder', 5)
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $this->assertDatabaseHas('categories', ['name_en' => 'Desserts', 'name_lo' => 'ຂອງຫວານ', 'sort_order' => 5]);
    }

    public function test_category_requires_both_names(): void
    {
        Volt::test('admin.menu')
            ->call('newCategory')
            ->set('categoryNameLo', '')
            ->set('categoryNameEn', '')
            ->call('saveCategory')
            ->assertHasErrors(['categoryNameLo', 'categoryNameEn']);
    }

    public function test_admin_can_edit_a_category(): void
    {
        $category = Category::factory()->create(['name_en' => 'Old']);

        Volt::test('admin.menu')
            ->call('editCategory', $category->id)
            ->assertSet('categoryNameEn', 'Old')
            ->set('categoryNameEn', 'New')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertSame('New', $category->fresh()->name_en);
    }

    public function test_category_with_items_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        MenuItem::factory()->for($category)->create();

        Volt::test('admin.menu')
            ->call('deleteCategory', $category->id)
            ->assertHasErrors('category');

        $this->assertModelExists($category);
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        Volt::test('admin.menu')->call('deleteCategory', $category->id);

        $this->assertModelMissing($category);
    }

    public function test_admin_can_create_a_menu_item_with_photo(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        Volt::test('admin.menu')
            ->call('selectCategory', $category->id)
            ->call('newItem')
            ->set('itemNameLo', 'ໄກ່ທອດ')
            ->set('itemNameEn', 'Fried Chicken')
            ->set('itemPrice', '40000')
            ->set('itemImage', UploadedFile::fake()->image('chicken.jpg'))
            ->call('saveItem')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal');

        $item = MenuItem::where('name_en', 'Fried Chicken')->first();

        $this->assertNotNull($item);
        $this->assertSame($category->id, $item->category_id);
        $this->assertEquals(40000, $item->price);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_menu_item_requires_names_and_price(): void
    {
        $category = Category::factory()->create();

        Volt::test('admin.menu')
            ->call('selectCategory', $category->id)
            ->call('newItem')
            ->set('itemPrice', '')
            ->call('saveItem')
            ->assertHasErrors(['itemNameLo', 'itemNameEn', 'itemPrice']);
    }

    public function test_admin_can_edit_a_menu_item(): void
    {
        $item = MenuItem::factory()->create(['price' => 10000]);

        Volt::test('admin.menu')
            ->call('selectCategory', $item->category_id)
            ->call('editItem', $item->id)
            ->assertSet('itemPrice', '10000')
            ->set('itemPrice', '12000')
            ->set('itemIsActive', false)
            ->call('saveItem')
            ->assertHasNoErrors();

        $item->refresh();

        $this->assertEquals(12000, $item->price);
        $this->assertFalse($item->is_active);
    }

    public function test_admin_can_toggle_item_availability(): void
    {
        $item = MenuItem::factory()->create(['is_available' => true]);

        Volt::test('admin.menu')->call('toggleAvailable', $item->id);
        $this->assertFalse($item->fresh()->is_available);

        Volt::test('admin.menu')->call('toggleAvailable', $item->id);
        $this->assertTrue($item->fresh()->is_available);
    }

    public function test_deleting_an_item_removes_its_photo(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('a.jpg')->store('menu', 'public');
        $item = MenuItem::factory()->create(['image_path' => $path]);

        Volt::test('admin.menu')->call('deleteItem', $item->id);

        $this->assertModelMissing($item);
        Storage::disk('public')->assertMissing($path);
    }
}
