<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics' => 'Gadgets, cables and accessories',
            'Office Supplies' => 'Paper, pens and office stuff',
            'Furniture' => 'Desks, chairs and shelves',
            'Kitchenware' => 'Pots, pans and utensils',
            'Tools' => 'Hand tools and power elec tools',
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
