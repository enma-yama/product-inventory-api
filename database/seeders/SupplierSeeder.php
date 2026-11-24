<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'NSK', 'email' => 'nsk@gmail.com', 'phone' => '0121234567'],
            ['name' => 'Trade', 'email' => 'trade@gmail.com', 'phone' => '0121234561'],
            ['name' => 'Supply', 'email' => 'supply@gmail.com', 'phone' => '0121234562'],
            ['name' => 'Imports', 'email' => 'import@gmail.com', 'phone' => '0121234563'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['email' => $supplier['email']], $supplier);
        }
    }
}
