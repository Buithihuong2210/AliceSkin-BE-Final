<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipping;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\CartController;
use Illuminate\Database\QueryException;
use Exception;
use Carbon\Carbon;
use App\Models\ShoppingCart;
use App\Models\OrderItem;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'shipping_id' => 'required|exists:shippings,id',
                'shipping_address' => 'required|string',
                'voucher_id' => 'nullable|exists:vouchers,voucher_id',
                'payment_method' => 'required|in:Cash on Delivery,VNpay Payment',
            ]);

            $userId = auth()->id();

            $cart = ShoppingCart::with('items.product')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json(['error' => 'Cart not found or cart is empty.'], 404);
            }

            if ($cart->items->isEmpty()) {
                return response()->json(['error' => 'Cart is empty.'], 400);
            }

            $cartController = new CartController();
            $cartData = $cartController->getCartWithSubtotal($cart);
            $subtotalOfCart = floatval($cartData['subtotal']);

            $shipping = Shipping::findOrFail($request->shipping_id);
            $shippingCost = floatval($shipping->shipping_amount);

            $discountAmount = 0;
            $voucherId = null;

            if ($request->voucher_id) {
                $voucher = Voucher::findOrFail($request->voucher_id);
                if ($voucher->status === 'active' && now()->between($voucher->start_date, $voucher->expiry_date)) {
                    $discountAmount = floatval($voucher->discount_amount);
                    $voucherId = $voucher->voucher_id;
                }
            }

            $totalAmount = $subtotalOfCart + $shippingCost - $discountAmount;

            $orderStatus = 'Pending';
            $paymentStatus = 'Pending';

            if ($request->payment_method == 'Cash on Delivery') {
                $orderStatus = 'Waiting for Delivery';
            } else {
                $paymentStatus = 'Waiting for Payment';
            }

            $order = Order::create([
                'user_id' => $userId,
                'subtotal_of_cart' => round($subtotalOfCart, 2),
                'total_amount' => round($totalAmount, 2),
                'shipping_id' => $request->shipping_id,
                'voucher_id' => $voucherId,
                'shipping_name' => $shipping->name,
                'shipping_cost' => $shippingCost,
                'shipping_address' => $request->shipping_address,
                'payment_method' => $request->payment_method,
                'payment_status' => $paymentStatus,
                'status' => $orderStatus,
                'order_date' => now(),
                'discount' => $discountAmount,
            ]);

            $processingDays = 2;
            $shippingDays = 3;
            $expectedDeliveryDate = $this->calculateExpectedDeliveryDate($order->order_date, $processingDays, $shippingDays);

            $order->update(['expected_delivery_date' => $expectedDeliveryDate]);

            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                if ($product->quantity < $cartItem->quantity) {
                    DB::rollBack();
                    return response()->json(['error' => "Insufficient inventory for product: {$product->name}"], 400);
                }

                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $product->product_id,
                    'quantity' => $cartItem->quantity,
                    'price' => floatval($cartItem->price),
                ]);

                $product->quantity -= $cartItem->quantity;
                $product->save();

            }

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully!',
                'user_id' => $order->user_id,
                'shipping_address' => $order->shipping_address,
                'shipping_id' => $order->shipping_id,
                'voucher_id' => $order->voucher_id,
                'shipping_name' => $order->shipping_name,
                'subtotal_of_cart' => number_format($order->subtotal_of_cart, 2),
                'shipping_cost' => number_format($order->shipping_cost, 2),
                'discount_amount' => number_format($discountAmount, 2),
                'total_amount' => number_format($order->total_amount, 2),
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'status' => $orderStatus,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'id' => $order->order_id,
                'order_date' => $order->order_date,
                'expected_delivery_date' => $expectedDeliveryDate,
                'cart_items' => $cart->items->map(function($item) {
                    return [
                        'product_id' => $item->product->product_id,
                        'name' => $item->product->name,
                        'price' => number_format($item->product->discounted_price, 2),
                        'quantity' => $item->quantity,
                    ];
                }),
            ], 201);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    protected function calculateExpectedDeliveryDate($orderDate, $processingDays, $shippingDays)
    {
        $expectedDate = Carbon::parse($orderDate)->addDays($processingDays + $shippingDays);

        while ($expectedDate->isWeekend()) {
            $expectedDate->addDay(); // If it falls on weekend, add 1 day
        }
        return $expectedDate->format('Y-m-d');
    }

    public function showAll()
    {
        try {
            // Fetch all orders with related order items and products
            $orders = Order::with('orderItems.product', 'user')->get();

            // Return only relevant fields in the JSON response
            return response()->json($orders->map(function ($order) {
                return [
                    'order_id' => $order->order_id,
                    'user_id' => $order->user_id,
                    'user_name' => $order->user ? $order->user->name : 'N/A',
                    'shipping_address' => $order->shipping_address,
                    'shipping_id' => $order->shipping_id,
                    'voucher_id' => $order->voucher_id,
                    'shipping_name' => $order->shipping_name,
                    'subtotal_of_cart' => number_format($order->subtotal_of_cart, 2),
                    'shipping_cost' => number_format($order->shipping_cost, 2),
                    'total_amount' => number_format($order->total_amount, 2),
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'order_items' => $order->orderItems->map(function ($item) {
                        return [
                            'product_id' => $item->product->product_id ?? null,
                            'name' => $item->product->name ?? 'N/A',
                            'price' => number_format($item->price, 2),
                            'quantity' => $item->quantity,
                        ];
                    }),
                ];
            }));
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function updateOrderStatus(Request $request, $order_id)
    {
        $order = Order::find($order_id);
        if (!$order) {
            return response()->json(['error' => 'Đơn hàng không tồn tại.'], 404);
        }

        if ($order->status === 'Completed') {
            return response()->json(['error' => 'Đơn hàng đã hoàn thành, không thể cập nhật trạng thái.'], 400);
        }

        if ($order->payment_status === 'Waiting for Payment') {
            return response()->json(['error' => 'Đơn hàng chưa được thanh toán, trạng thái vẫn là Pending.'], 400);
        }

        // Kiểm tra phương thức thanh toán
        if ($order->payment_method === 'Cash on Delivery') {
            if ($order->status === 'Waiting for Delivery') {
                return response()->json(['error' => 'Đơn hàng đang chờ giao hàng, không thể cập nhật trạng thái.'], 400);
            } elseif ($order->status === 'Delivered') {
                $order->status = 'Completed';
            }
        } elseif ($order->payment_method === 'VNpay Payment') {
            if ($order->status === 'Waiting for Delivery') {
                return response()->json(['error' => 'Đơn hàng đang chờ giao hàng, không thể cập nhật trạng thái.'], 400);
            } elseif ($order->status === 'Delivered') {
                $order->status = 'Completed';
            }
        }

        if ($order->isDirty('status')) {
            $order->save();
        }

        return response()->json(['message' => 'Trạng thái đơn hàng đã được cập nhật thành công.', 'order' => $order], 200);
    }

    public function confirmDelivery($order_id)
    {
        try {
            $order = DB::table('orders')->where('order_id', $order_id)->first();

            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            $paymentMethod = trim($order->payment_method);

            // Kiểm tra phương thức thanh toán
            if ($paymentMethod === 'Cash on Delivery') {
                if ($order->status === 'Delivered') {
                    return response()->json(['error' => 'Order has already been delivered'], 400);
                }

                DB::table('orders')->where('order_id', $order_id)->update([
                    'status' => 'Delivered',
                    'payment_status' => 'Paid',
                ]);

                $amount = number_format($order->total_amount, 0) . ' VND';

                return response()->json([
                    'message' => 'Order delivered and payment confirmed successfully',
                    'amount' => $amount,
                ], 200);

            } elseif ($paymentMethod === 'VNpay Payment') {
                if ($order->status === 'Delivered') {
                    return response()->json(['error' => 'Order has already been delivered'], 400);
                }

                $paymentStatus = $this->checkVnPayPaymentStatus($order_id);

                // Nếu thanh toán thất bại
                if ($paymentStatus === 'Failed') {
                    DB::table('orders')->where('order_id', $order_id)->update([
                        'status' => 'Failed',
                        'payment_status' => 'Failed',
                    ]);

                    return response()->json([
                        'message' => 'VNPay payment failed, order status updated to failed.',
                    ], 400);
                }

                DB::table('orders')->where('order_id', $order_id)->update([
                    'status' => 'Delivered',
                ]);

                $amount = number_format($order->total_amount, 0) . ' VND';

                return response()->json([
                    'message' => 'Order delivered successfully',
                    'amount' => $amount,
                ], 200);

            } else {
                return response()->json(['error' => 'Invalid payment method'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to confirm delivery: ' . $e->getMessage()], 500);
        }
    }

    public function getTotalPaymentsForBothMethods()
    {
        try {
            $totalVNPay = DB::table('orders')
                ->where('payment_method', 'VNpay Payment')
                ->where('status', 'Completed')
                ->sum('total_amount');

            $totalCOD = DB::table('orders')
                ->where('payment_method', 'Cash on Delivery')
                ->where('status', 'Completed')
                ->sum('total_amount');

            $totalAmount = $totalVNPay + $totalCOD;

            $formattedVNPay = number_format($totalVNPay, 0) . ' VND';
            $formattedCOD = number_format($totalCOD, 0) . ' VND';
            $formattedTotal = number_format($totalAmount, 0) . ' VND';

            return response()->json([
                'total_VNPay' => $formattedVNPay,
                'total_COD' => $formattedCOD,
                'total_amount' => $formattedTotal
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch total payments: ' . $e->getMessage()], 500);
        }
    }
    public function getCanceledOrders()
    {
        try {
            $canceledOrders = Order::where('status', 'Canceled')->get();

            if ($canceledOrders->isEmpty()) {
                return response()->json(['message' => 'No canceled orders found.'], 200);
            }

            return response()->json([
                'message' => 'Canceled orders retrieved successfully.',
                'orders' => $canceledOrders
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to retrieve canceled orders: ' . $e->getMessage()], 500);
        }
    }

    public function getOrderItems($order_id)
    {
        $order = Order::with('orderItems.product')->find($order_id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->orderItems->isEmpty()) {
            return response()->json(['message' => 'No order items found for this order.'], 404);
        }

        $orderItems = $order->orderItems->map(function($item) {
            return [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ];
        });

        return response()->json($orderItems);
    }
    public function checkVnPayPaymentStatus($order_id)
    {
        $status = 'Success';

        return $status;
    }

    public function viewAllOrdersByUserId($userId)
    {
        try {
            $orders = Order::with('orderItems.product')
                ->where('user_id', $userId)
                ->get();

            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No orders found for this user'], 404);
            }

            return response()->json($orders->map(function ($order) {
                return [
                    'order_id' => $order->order_id,
                    'order_date' => Carbon::parse($order->order_date)->format('Y-m-d'), // Chuyển đổi đúng cách
                    'total_amount' => number_format($order->total_amount, 2),
                    'shipping_address' => $order->shipping_address,
                    'status' => $order->status,
                    'cart_items' => $order->orderItems->map(function ($item) {
                        return [
                            'product_id' => $item->product->product_id ?? null,
                            'product_name' => $item->product->name ?? 'N/A',
                            'quantity' => $item->quantity,
                            'price' => number_format($item->product->discounted_price, 2),
                            'image' => $item->product->image ?? 'No image available',
                        ];
                    }),
                ];
            }));

        } catch (QueryException $e) {
            return response()->json(['error' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Đã xảy ra lỗi không mong muốn: ' . $e->getMessage()], 500);
        }
    }

    public function getOrderById($orderId)
    {
        try {
            $order = Order::with('orderItems.product', 'voucher', 'shipping')
                ->where('order_id', $orderId)
                ->firstOrFail();

            $orderDate = Carbon::parse($order->order_date);

            $expectedDeliveryDate = $orderDate->addDays(5)->format('Y-m-d');

            return response()->json([
                'order_id' => $order->order_id,
                'order_date' => $orderDate->toIso8601String(),
                'expected_delivery_date' => $expectedDeliveryDate,
                'total_amount' => number_format($order->total_amount, 2),
                'shipping_address' => $order->shipping_address,
                'status' => $order->status,
                'voucher' => $order->voucher ? [
                    'code' => $order->voucher->code,
                    'discount_amount' => number_format($order->voucher->discount_amount, 2),
                    'status' => $order->voucher->status,
                ] : null,
                'shipping' => $order->shipping ? [
                    'name' => $order->shipping->name,
                    'shipping_amount' => number_format($order->shipping->shipping_amount, 2),
                    'method' => $order->shipping->method,
                ] : null,
                'cart_items' => $order->orderItems->map(function ($item) {
                    return [
                        'product_id' => $item->product->product_id ?? null,
                        'product_name' => $item->product->name ?? 'N/A',
                        'quantity' => $item->quantity,
                        'price' => number_format($item->product->discounted_price, 2),
                        'image' => $item->product->image ?? 'No image available',
                    ];
                }),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Order not found'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Unexpected error: ' . $e->getMessage()], 500);
        }
    }

}