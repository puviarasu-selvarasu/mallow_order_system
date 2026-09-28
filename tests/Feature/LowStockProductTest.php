<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_products_below_the_configured_threshold_in_stock_order(): void
    {
        config(['inventory.low_stock_threshold' => 10]);

        $outOfStock = Product::factory()->create([
            'name' => 'Out of stock',
            'stock_quantity' => 0,
        ]);
        $belowThreshold = Product::factory()->create([
            'name' => 'Below threshold',
            'stock_quantity' => 9,
        ]);
        $atThreshold = Product::factory()->create([
            'name' => 'At threshold',
            'stock_quantity' => 10,
        ]);
        $aboveThreshold = Product::factory()->create([
            'name' => 'Above threshold',
            'stock_quantity' => 11,
        ]);

        $response = $this->getJson('/api/products/low-stock');

        $response->assertOk()
            ->assertJsonPath('threshold', 10);

        $returnedIds = collect($response->json('products'))->pluck('id');

        $this->assertCount(2, $response->json('products'));
        $this->assertSame([$outOfStock->id, $belowThreshold->id], $returnedIds->all());
        $this->assertSame(0, $response->json('products.0.stock_quantity'));
        $this->assertSame(9, $response->json('products.1.stock_quantity'));
        $this->assertFalse($returnedIds->contains($atThreshold->id));
        $this->assertFalse($returnedIds->contains($aboveThreshold->id));
    }

    public function test_it_respects_a_different_low_stock_threshold(): void
    {
        config(['inventory.low_stock_threshold' => 5]);

        $included = Product::factory()->create([
            'stock_quantity' => 4,
        ]);
        Product::factory()->create([
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'stock_quantity' => 6,
        ]);

        $response = $this->getJson('/api/products/low-stock');

        $response->assertOk()
            ->assertJsonPath('threshold', 5);

        $this->assertCount(1, $response->json('products'));
        $this->assertSame($included->id, $response->json('products.0.id'));
        $this->assertSame(4, $response->json('products.0.stock_quantity'));
    }
}
