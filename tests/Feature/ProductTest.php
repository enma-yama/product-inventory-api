<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_products_are_paginated(): void
    {
        Product::factory()->count(20)->create();

        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_can_create_a_product_with_suppliers(): void
    {
        $category = Category::factory()->create();
        $suppliers = Supplier::factory()->count(2)->create();

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'USB-C Cable',
            'sku' => 'usb-100',
            'description' => '2m braided cable',
            'price' => 12.5,
            'stock' => 25,
            'supplier_ids' => $suppliers->pluck('id')->all(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'USB-C Cable')
            ->assertJsonPath('data.sku', 'USB-100')
            ->assertJsonPath('data.stock', 25)
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonCount(2, 'data.suppliers');

        $this->assertDatabaseHas('products', ['sku' => 'USB-100']);
        $this->assertDatabaseCount('product_supplier', 2);
    }

    public function test_create_product_validates_input(): void
    {
        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'name', 'sku', 'price', 'stock']);

        $this->postJson('/api/products', [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Broken product',
            'sku' => 'BRK-1',
            'price' => -5,
            'stock' => 1.5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'stock']);
    }

    public function test_sku_must_be_unique(): void
    {
        $existing = Product::factory()->create(['sku' => 'DUP-001']);

        $this->postJson('/api/products', [
            'category_id' => $existing->category_id,
            'name' => 'Another thing',
            'sku' => 'dup-001',
            'price' => 10,
            'stock' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_can_show_a_product(): void
    {
        $product = Product::factory()->create();
        $product->suppliers()->attach(Supplier::factory()->create());

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.category.id', $product->category_id)
            ->assertJsonCount(1, 'data.suppliers');
    }

    public function test_missing_product_returns_404(): void
    {
        $this->getJson('/api/products/9999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_can_update_a_product(): void
    {
        $product = Product::factory()->create(['name' => 'Old name', 'stock' => 5]);

        $this->putJson("/api/products/{$product->id}", [
            'name' => 'New name',
            'stock' => 40,
        ])->assertOk()
            ->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.stock', 40);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'New name',
            'stock' => 40,
        ]);
    }

    public function test_update_can_keep_its_own_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'KEEP-001']);

        $this->patchJson("/api/products/{$product->id}", [
            'sku' => 'KEEP-001',
            'price' => 99,
        ])->assertOk();
    }

    public function test_update_can_replace_suppliers(): void
    {
        $product = Product::factory()->create();
        $product->suppliers()->attach(Supplier::factory()->count(2)->create());
        $newSupplier = Supplier::factory()->create();

        $this->patchJson("/api/products/{$product->id}", [
            'supplier_ids' => [$newSupplier->id],
        ])->assertOk()
            ->assertJsonCount(1, 'data.suppliers')
            ->assertJsonPath('data.suppliers.0.id', $newSupplier->id);
    }

    public function test_delete_is_a_soft_delete(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted($product);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->getJson('/api/products')->assertJsonCount(0, 'data');
    }
}
