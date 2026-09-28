<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_product_catalogue_ordered_by_name(): void
    {
        $banana = Product::factory()->create([
            'name' => 'Banana Product',
            'code' => 'BAN-001',
            'price' => 200.00,
            'tax_percentage' => 18.00,
            'stock_quantity' => 5,
        ]);

        $apple = Product::factory()->create([
            'name' => 'Apple Product',
            'code' => 'APP-001',
            'price' => 100.00,
            'tax_percentage' => 5.00,
            'stock_quantity' => 10,
        ]);

        $zeroStock = Product::factory()->create([
            'name' => 'Zero Stock Product',
            'code' => 'ZERO-001',
            'price' => 300.00,
            'tax_percentage' => 12.00,
            'stock_quantity' => 0,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk();

        $this->assertCount(3, $response->json('products'));

        $this->assertSame(
            [$apple->id, $banana->id, $zeroStock->id],
            collect($response->json('products'))->pluck('id')->all()
        );

        $response
            ->assertJsonPath('products.0.name', 'Apple Product')
            ->assertJsonPath('products.0.code', 'APP-001')
            ->assertJsonPath('products.0.price', '100.00')
            ->assertJsonPath('products.0.tax_percentage', '5.00')
            ->assertJsonPath('products.0.stock_quantity', 10)
            ->assertJsonPath('products.2.stock_quantity', 0);

        $this->assertArrayNotHasKey(
            'created_at',
            $response->json('products.0')
        );

        $this->assertArrayNotHasKey(
            'updated_at',
            $response->json('products.0')
        );
    }
}