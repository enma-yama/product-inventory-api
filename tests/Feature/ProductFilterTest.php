<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_filter_by_category(): void
    {
        $books = Category::factory()->create();
        $toys = Category::factory()->create();

        Product::factory()->count(3)->create(['category_id' => $books->id]);
        Product::factory()->count(2)->create(['category_id' => $toys->id]);

        $this->getJson("/api/products?category_id={$toys->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filter_by_price_range(): void
    {
        Product::factory()->create(['price' => 5]);
        $middle = Product::factory()->create(['price' => 50]);
        Product::factory()->create(['price' => 500]);

        $this->getJson('/api/products?min_price=10&max_price=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $middle->id);
    }

    public function test_filter_by_stock_level(): void
    {
        Product::factory()->outOfStock()->create();
        Product::factory()->create(['stock' => 5]);
        Product::factory()->create(['stock' => 150]);

        $this->getJson('/api/products?in_stock=1')
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/products?low_stock=1')
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/products?min_stock=100')
            ->assertJsonCount(1, 'data');
    }

    public function test_filters_can_be_combined(): void
    {
        $category = Category::factory()->create();

        $match = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 20,
            'stock' => 3,
        ]);
        // right category, wrong price
        Product::factory()->create(['category_id' => $category->id, 'price' => 400, 'stock' => 3]);
        // right price, wrong category
        Product::factory()->create(['price' => 20, 'stock' => 3]);

        $this->getJson("/api/products?category_id={$category->id}&max_price=100&low_stock=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_search_matches_name_or_sku(): void
    {
        Product::factory()->create(['name' => 'Wireless Mouse', 'sku' => 'MSE-001']);
        Product::factory()->create(['name' => 'Desk Lamp', 'sku' => 'LMP-001']);

        $this->getJson('/api/products?search=mouse')->assertJsonCount(1, 'data');
        $this->getJson('/api/products?search=LMP')->assertJsonCount(1, 'data');
    }

    public function test_bad_filter_values_are_rejected(): void
    {
        $this->getJson('/api/products?per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/products?category_id=9999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }
}
