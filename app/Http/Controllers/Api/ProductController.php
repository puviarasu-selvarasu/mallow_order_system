<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{

    public function index(): JsonResponse
    {
        $products = Product::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'price',
                'tax_percentage',
                'stock_quantity',
            ]);

        return response()->json([
            'products' => $products,
        ]);
    }
    
    public function lowStock(): JsonResponse
    {
        $threshold = (int) config('inventory.low_stock_threshold');

        $products = Product::query()
            ->where('stock_quantity', '<', $threshold)
            ->orderBy('stock_quantity')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'price',
                'tax_percentage',
                'stock_quantity',
            ]);

        return response()->json([
            'threshold' => $threshold,
            'products' => $products,
        ]);
    }
}
