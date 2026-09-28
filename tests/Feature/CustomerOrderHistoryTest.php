<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_customers_orders_newest_first_with_items(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $otherCustomer = Customer::factory()->create([
            'email' => 'other@example.com',
        ]);

        $product = Product::factory()->create();

        $olderOrder = Order::query()->create([
            'customer_id' => $customer->id,
            'subtotal' => 100.00,
            'tax_amount' => 18.00,
            'grand_total' => 118.00,
        ]);
        $olderOrder->forceFill(['created_at' => now()->subHour()])->save();

        OrderItem::query()->create([
            'order_id' => $olderOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_percentage' => 18.00,
            'tax_amount' => 18.00,
            'line_total' => 118.00,
        ]);

        $newerOrder = Order::query()->create([
            'customer_id' => $customer->id,
            'subtotal' => 50.00,
            'tax_amount' => 2.50,
            'grand_total' => 52.50,
        ]);
        $newerOrder->forceFill(['created_at' => now()])->save();

        OrderItem::query()->create([
            'order_id' => $newerOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25.00,
            'tax_percentage' => 5.00,
            'tax_amount' => 2.50,
            'line_total' => 52.50,
        ]);

        $otherOrder = Order::query()->create([
            'customer_id' => $otherCustomer->id,
            'subtotal' => 999.00,
            'tax_amount' => 0.00,
            'grand_total' => 999.00,
        ]);

        $response = $this->getJson('/api/customers/orders?email=john@example.com');

        $response->assertOk()
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.name', 'John Doe')
            ->assertJsonPath('customer.email', 'john@example.com');

        $this->assertCount(2, $response->json('orders'));
        $this->assertSame($newerOrder->id, $response->json('orders.0.id'));
        $this->assertSame($olderOrder->id, $response->json('orders.1.id'));
        $this->assertNotContains($otherOrder->id, collect($response->json('orders'))->pluck('id'));

        $response->assertJsonPath('orders.0.subtotal', '50.00')
            ->assertJsonPath('orders.0.tax_amount', '2.50')
            ->assertJsonPath('orders.0.grand_total', '52.50')
            ->assertJsonPath('orders.0.items.0.product_id', $product->id)
            ->assertJsonPath('orders.0.items.0.quantity', 2)
            ->assertJsonPath('orders.0.items.0.unit_price', '25.00')
            ->assertJsonPath('orders.0.items.0.tax_percentage', '5.00')
            ->assertJsonPath('orders.0.items.0.tax_amount', '2.50')
            ->assertJsonPath('orders.0.items.0.line_total', '52.50');
    }

    public function test_it_returns_an_empty_orders_list_for_a_customer_with_no_orders(): void
    {
        Customer::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $response = $this->getJson('/api/customers/orders?email=jane@example.com');

        $response->assertOk()
            ->assertJsonPath('customer.email', 'jane@example.com')
            ->assertJsonPath('orders', []);
    }

    public function test_it_returns_not_found_for_an_unknown_customer_email(): void
    {
        $response = $this->getJson('/api/customers/orders?email=missing@example.com');

        $response->assertNotFound()
            ->assertJsonPath('message', 'Customer not found.');
    }

    public function test_it_validates_the_email_query_parameter(): void
    {
        $this->getJson('/api/customers/orders')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->getJson('/api/customers/orders?email=not-an-email')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
