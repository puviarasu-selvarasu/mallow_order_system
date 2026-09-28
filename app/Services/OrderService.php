<?php

namespace App\Services;

use App\Jobs\SendOrderConfirmationJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * @param  array{customer: array{name: string, email: string}, items: array<int, array{product_id: int, quantity: int}>}  $payload
     */
    public function create(array $payload): Order
    {
        $order = DB::transaction(function () use ($payload) {
            $customer = Customer::firstOrCreate(
                ['email' => $payload['customer']['email']],
                ['name' => $payload['customer']['name']],
            );

            $itemsByProductId = collect($payload['items'])->keyBy('product_id');
            $productIds = $itemsByProductId->keys()->sort()->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['One or more products could not be found.'],
                ]);
            }

            $lineItems = [];
            $orderSubtotal = 0.0;
            $orderTax = 0.0;

            foreach ($productIds as $productId) {
                $product = $products->get($productId);
                $quantity = (int) $itemsByProductId->get($productId)['quantity'];

                if ($product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for product ID {$productId}."],
                    ]);
                }

                $unitPrice = round((float) $product->price, 2);
                $taxPercentage = round((float) $product->tax_percentage, 2);
                $lineSubtotal = round($unitPrice * $quantity, 2);
                $lineTax = round($lineSubtotal * $taxPercentage / 100, 2);
                $lineTotal = round($lineSubtotal + $lineTax, 2);

                $lineItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_percentage' => $taxPercentage,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ];

                $orderSubtotal = round($orderSubtotal + $lineSubtotal, 2);
                $orderTax = round($orderTax + $lineTax, 2);
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'subtotal' => $orderSubtotal,
                'tax_amount' => $orderTax,
                'grand_total' => round($orderSubtotal + $orderTax, 2),
            ]);

            foreach ($lineItems as $lineItem) {
                $order->items()->create([
                    'product_id' => $lineItem['product']->id,
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'tax_percentage' => $lineItem['tax_percentage'],
                    'tax_amount' => $lineItem['tax_amount'],
                    'line_total' => $lineItem['line_total'],
                ]);
            }

            foreach ($lineItems as $lineItem) {
                $lineItem['product']->decrement('stock_quantity', $lineItem['quantity']);
            }

            return $order->load(['customer', 'items']);
        });

        SendOrderConfirmationJob::dispatch($order->id);

        return $order;
    }
}
