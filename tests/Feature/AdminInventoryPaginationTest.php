<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        // Create 25 items to ensure at least 3 pages
        for ($i = 1; $i <= 25; $i++) {
            InventoryItem::create([
                'name' => 'Item ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'category' => $i <= 15 ? 'flowers' : 'decor',
                'is_perishable' => $i <= 15,
                'current_stock' => 10,
                'min_stock' => 5,
                'unit_cost' => 10,
                'unit' => 'pcs',
            ]);
        }
        
        // Item with low stock to test status filter
        InventoryItem::create([
            'name' => 'Low Stock Item',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 3,
            'min_stock' => 5,
            'unit_cost' => 10,
            'unit' => 'pcs',
        ]);
        
        // Item with shortage
        InventoryItem::create([
            'name' => 'Shortage Item',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => -2,
            'min_stock' => 5,
            'unit_cost' => 10,
            'unit' => 'pcs',
        ]);
    }

    public function test_default_pagination_is_10_items(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk();
        $response->assertSeeText('Showing');
        $response->assertSeeText('10');
        $response->assertSeeText('27');
        $response->assertSee('page=2');
    }

    public function test_page_2_returns_the_next_records(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['page' => 2]));
        $response->assertOk();
        $response->assertSeeText('11');
        $response->assertSeeText('20');
        $response->assertSeeText('27');
    }

    public function test_search_works_with_pagination(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['search' => 'Item 1']));
        $response->assertOk();
        // Should match Item 10 through 19 (10 items)
        $response->assertSeeText('of');
        $response->assertSeeText('10');
    }

    public function test_category_filter_works_with_pagination(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['category' => 'flowers']));
        $response->assertOk();
        $response->assertSeeText('16');
    }

    public function test_status_filter_works_with_pagination(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['status' => 'low']));
        $response->assertOk();
        $response->assertSeeText('1');
        $response->assertSee('Low Stock Item');
    }

    public function test_perishable_filter_works_with_pagination(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['perishable' => 'yes']));
        $response->assertOk();
        $response->assertSeeText('16');
    }

    public function test_combined_filters_work_with_pagination(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', [
            'category' => 'decor',
            'status' => 'shortage'
        ]));
        $response->assertOk();
        $response->assertSeeText('1');
        $response->assertSee('Shortage Item');
    }

    public function test_filter_query_parameters_persist_when_moving_between_pages(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['category' => 'flowers']));
        $response->assertOk();
        $response->assertSee('category=flowers');
        $response->assertSee('page=2');
    }

    public function test_summary_counts_remain_based_on_unfiltered_dataset(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['category' => 'decor']));
        $response->assertOk();
        $response->assertSee('>27<', false); 
        $response->assertSee('>25<', false); 
        $response->assertSee('>1<', false); 
    }
}
