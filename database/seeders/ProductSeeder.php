<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::exists()) {
            return;
        }

        $electronics = Category::where('name', 'Electronics')->first();
        $office = Category::where('name', 'Office Supplies')->first();
        $tools = Category::where('name', 'Tools')->first();

        $nsk = Supplier::where('name', 'NSK')->first();
        $trade = Supplier::where('name', 'Trade')->first();
        $supply = Supplier::where('name', 'Supply')->first();

        $mouse = Product::create([
            'category_id' => $electronics->id,
            'name' => 'Wireless Mouse',
            'sku' => 'ELC-001',
            'description' => 'USB wireless mouse',
            'price' => 24.90,
            'stock' => 65,
        ]);
        $mouse->suppliers()->attach([$nsk->id, $trade->id]);

        $keyboard = Product::create([
            'category_id' => $electronics->id,
            'name' => 'Mechanical Keyboard',
            'sku' => 'ELC-002',
            'description' => 'Tenkeyless, blue switches',
            'price' => 79.00,
            'stock' => 8,
        ]);
        $keyboard->suppliers()->attach([$trade->id]);

        $paper = Product::create([
            'category_id' => $office->id,
            'name' => 'A4 Paper Ream',
            'sku' => 'OFF-001',
            'description' => '500 sheets, 80gsm',
            'price' => 4.50,
            'stock' => 300,
        ]);
        $paper->suppliers()->attach([$supply->id]);

        $stapler = Product::create([
            'category_id' => $office->id,
            'name' => 'Stapler',
            'sku' => 'OFF-002',
            'description' => null,
            'price' => 8.90,
            'stock' => 0,
        ]);
        $stapler->suppliers()->attach([$supply->id]);

        $drill = Product::create([
            'category_id' => $tools->id,
            'name' => 'Cordless Drill 18V',
            'sku' => 'TLS-001',
            'description' => 'Comes with 2 batteries',
            'price' => 89.00,
            'stock' => 11,
        ]);
        $drill->suppliers()->attach([$nsk->id]);

        $suppliers = Supplier::all();

        Product::factory(25)->recycle(Category::all())->create()->each(function ($product) use ($suppliers) {
            $product->suppliers()->attach($suppliers->random(rand(1, 2))->pluck('id'));
        });
    }
}
