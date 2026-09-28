<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StoreOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_multi_product_order_with_correct_totals_snapshots_and_stock(): void
    {
        $productA = Product::factory()->create([
            'name' => 'Product A',
            'price' => 450.95,
            'tax_percentage' => 28,
            'stock_quantity' => 67,
        ]);

        $productB = Product::factory()->create([
            'name' => 'Product B',
            'price' => 144.98,
            'tax_percentage' => 5,
            'stock_quantity' => 55,
        ]);

        Queue::fake();

        $payload = [
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('customers', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);

        $order = Order::query()->first();

        $this->assertSame('1336.84', (string) $order->subtotal);
        $this->assertSame('274.28', (string) $order->tax_amount);
        $this->assertSame('1611.12', (string) $order->grand_total);
        $this->assertSame('1336.84', (string) $response->json('subtotal'));
        $this->assertSame('274.28', (string) $response->json('tax_amount'));
        $this->assertSame('1611.12', (string) $response->json('grand_total'));

        $itemA = OrderItem::query()->where('product_id', $productA->id)->first();
        $this->assertNotNull($itemA);
        $this->assertSame(2, (int) $itemA->quantity);
        $this->assertSame('450.95', (string) $itemA->unit_price);
        $this->assertSame('28.00', (string) $itemA->tax_percentage);
        $this->assertSame('252.53', (string) $itemA->tax_amount);
        $this->assertSame('1154.43', (string) $itemA->line_total);

        $itemB = OrderItem::query()->where('product_id', $productB->id)->first();
        $this->assertNotNull($itemB);
        $this->assertSame(3, (int) $itemB->quantity);
        $this->assertSame('144.98', (string) $itemB->unit_price);
        $this->assertSame('5.00', (string) $itemB->tax_percentage);
        $this->assertSame('21.75', (string) $itemB->tax_amount);
        $this->assertSame('456.69', (string) $itemB->line_total);

        $this->assertSame(65, $productA->fresh()->stock_quantity);
        $this->assertSame(52, $productB->fresh()->stock_quantity);

        Queue::assertPushed(SendOrderConfirmationJob::class, function (SendOrderConfirmationJob $job) use ($order) {
            return $job->orderId === $order->id;
        });
    }

    public function test_insufficient_stock_rolls_back_the_entire_order_including_the_new_customer(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 5,
        ]);

        Queue::fake();

        $email = 'new-customer@example.com';

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => 'New Customer',
                'email' => $email,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 6,
                ],
            ],
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseMissing('customers', [
            'email' => $email,
        ]);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        Queue::assertNothingPushed();
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 0,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_product_ids_in_the_same_request_are_rejected(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.product_id']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }
    public function test_customer_name_cannot_exceed_255_characters(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => str_repeat('A', 256),
                'email' => 'customer@example.com',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer.name']);
    }
}
