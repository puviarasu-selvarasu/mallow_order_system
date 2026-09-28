<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Customer::factory()->count(10)->create();

        Product::factory()->count(20)->create();
    }

}
