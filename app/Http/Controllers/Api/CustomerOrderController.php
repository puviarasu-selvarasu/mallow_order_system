<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerOrderHistoryRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class CustomerOrderController extends Controller
{
    public function history(CustomerOrderHistoryRequest $request): JsonResponse
    {
        $customer = Customer::query()
            ->where('email', $request->validated('email'))
            ->with([
                'orders' => fn ($query) => $query
                    ->latest()
                    ->with('items'),
            ])
            ->first();

        if ($customer === null) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'orders' => $customer->orders->map(fn (Order $order) => [
                'id' => $order->id,
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'grand_total' => $order->grand_total,
                'created_at' => $order->created_at,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_percentage' => $item->tax_percentage,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                ])->values(),
            ])->values(),
        ]);
    }
}
