<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    /* List orders for the authenticated user.*/
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.product'])
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function update(StoreOrderRequest $request, Order $order): JsonResponse
    {
        try {
            $data = $request->toDto();
            $order = $this->syncOrder($request, $order, $data, false);

            return response()->json([
                'message' => 'Order updated successfully.',
                'order' => $order,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Order update failed. Please try again.',
                'error_code' => 'order_update_failed',
            ], 500);
        }
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        try {
            $this->deleteOrder($request, $order);

            return response()->json([
                'message' => 'Order deleted successfully.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Order deletion failed. Please try again.',
                'error_code' => 'order_deletion_failed',
            ], 500);
        }
    }
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $data = $request->toDto();

            $order = $this->syncOrder($request, $order = null, $data, true);

            return response()->json([
                'message' => 'Order created successfully.',
                'order' => $order,
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Order creation failed. Please try again.',
                'error_code' => 'order_creation_failed',
            ], 500);
        }
    }

    private function syncOrder(Request $request, ?Order $order, mixed $data, bool $isNew): Order
    {
        /** @var Order $syncedOrder */
        $syncedOrder = DB::transaction(function () use ($request, $order, $data, $isNew) {
            $existingOrder = $isNew
                ? Order::query()->create([
                    'user_id' => $request->user()->id,
                    'total_price' => 0,
                ])
                : $this->ownedOrder($request, $order);

            $existingOrder->load('items');

            $productIds = collect($data->productIds())
                ->merge($existingOrder->items->pluck('product_id'))
                ->unique()
                ->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($existingOrder->items as $existingItem) {
                $products->get($existingItem->product_id)?->increment('stock', $existingItem->quantity);
            }

            if (! $isNew) {
                $existingOrder->items()->delete();
            }

            $totalAmount = 0;

            foreach ($data->items as $item) {
                $product = $products->get($item->productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more selected products could not be loaded.'],
                    ]);
                }

                if ($product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for product {$product->name}."],
                    ]);
                }

                $lineTotal = round((float) $product->price * $item->quantity, 2);
                $totalAmount += $lineTotal;

                $existingOrder->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'price' => $product->price,
                ]);

                $product->decrement('stock', $item->quantity);
            }

            $existingOrder->update([
                'total_price' => round($totalAmount, 2),
            ]);

            return $existingOrder->load('items.product');
        });

        return $syncedOrder;
    }

    private function deleteOrder(Request $request, Order $order): void
    {
        DB::transaction(function () use ($request, $order) {
            $ownedOrder = $this->ownedOrder($request, $order);
            $ownedOrder->load('items');

            $products = Product::query()
                ->whereIn('id', $ownedOrder->items->pluck('product_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($ownedOrder->items as $item) {
                $products->get($item->product_id)?->increment('stock', $item->quantity);
            }

            $ownedOrder->delete();
        });
    }

    private function ownedOrder(Request $request, Order $order): Order
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return $order;
    }
}