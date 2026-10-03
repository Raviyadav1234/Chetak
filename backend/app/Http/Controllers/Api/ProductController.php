<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Order;
use App\Http\Resources\ProductResource;
use App\Http\Requests\BookProductRequest;
use App\Jobs\ProcessPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index()
    {
        return ProductResource::collection(Product::all());
    }

    public function book(BookProductRequest $request, $id): JsonResponse
    {
        $quantity = $request->validated('quantity');

        try {
            DB::beginTransaction();

            // Pessimistic locking to prevent overselling
            $product = Product::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($product->stock < $quantity) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Not enough stock available.'
                ], 422);
            }

            // Decrement stock
            $product->stock -= $quantity;
            $product->save();

            // Create pending order
            $order = Order::create([
                'user_id' => $request->user()->id ?? 1, // Mock user ID for now if not authenticated fully
                'product_id' => $product->id,
                'quantity' => $quantity,
                'status' => 'pending',
            ]);

            DB::commit();

            // Dispatch payment job
            ProcessPayment::dispatch($order->id);

            return response()->json([
                'message' => 'Booking initiated successfully.',
                'order_id' => $order->id,
                'product' => new ProductResource($product)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred during booking.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
