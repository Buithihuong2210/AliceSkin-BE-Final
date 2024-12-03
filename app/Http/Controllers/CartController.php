<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShoppingCart;
use App\Models\CartItem;
use App\Models\Product;
use Exception;

class CartController extends Controller
{
    /**
     * List all items in the cart for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Find or create the active shopping cart for the authenticated user
            $cart = ShoppingCart::where('user_id', auth()->id())
                ->where('status', 'active') // Only get active cart
                ->firstOrCreate([
                    'user_id' => auth()->id(),
                    'status' => 'active'
                ]);

            // Get the cart with subtotal
            $cartData = $this->getCartWithSubtotal($cart);

            return response()->json($cartData, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving cart: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Add a product to the cart.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,product_id',
                'quantity' => 'required|integer|min:1',
            ]);

            $product = Product::findOrFail($request->product_id);

            $cart = ShoppingCart::where('user_id', auth()->id())
                ->where('status', 'active')
                ->firstOrCreate([
                    'user_id' => auth()->id(),
                    'status' => 'active'
                ]);

            $cartItem = $cart->items()->where('product_id', $request->product_id)->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $request->quantity;

                if ($newQuantity > $product->quantity) {
                    return response()->json([
                        'message' => 'Requested quantity exceeds available stock.',
                        'available_stock' => $product->quantity
                    ], 400);
                }

                $cartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $product->discounted_price * $newQuantity
                ]);
            } else {
                if ($request->quantity > $product->quantity) {
                    return response()->json([
                        'message' => 'Requested quantity exceeds available stock.',
                        'available_stock' => $product->quantity
                    ], 400);
                }

                $cart->items()->create([
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'price' => $product->discounted_price * $request->quantity,
                ]);
            }

            $subtotal = $cart->items()->sum('price');
            $cart->subtotal = number_format($subtotal, 2, '.', '');
            $cart->save();

            return response()->json($this->getCartWithSubtotal($cart), 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error updating cart: ' . $e->getMessage()], 500);
        }
    }

    public function completeCart()
        {
            try {
                $cart = ShoppingCart::where('user_id', auth()->id())
                    ->where('status', 'active')
                    ->first();

                if (!$cart) {
                    return response()->json(['message' => 'No active cart found.'], 404);
                }

                $cart->status = 'completed';
                $cart->save();

                return response()->json(['message' => 'Cart marked as completed successfully.'], 200);
            } catch (Exception $e) {
                return response()->json(['error' => 'Error completing cart: ' . $e->getMessage()], 500);
            }
        }

    /**
     * Show a specific cart item for the authenticated user.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $cart = ShoppingCart::with('items.product')->where('user_id', auth()->id())->first();

            if (!$cart) {
                return response()->json(['message' => 'Cart not found.'], 404);
            }

            $cartItem = $cart->items()->where('id', $id)->with('product')->first();

            if (!$cartItem) {
                return response()->json(['message' => 'Cart item not found.'], 404);
            }

            return response()->json($cartItem, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving cart item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the quantity of a cart item.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\CartItem $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, CartItem $item)
    {
        try {
            $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            $product = Product::findOrFail($item->product_id);

            $totalPrice = $product->discounted_price * $request->quantity;

            $item->update([
                'quantity' => $request->quantity,
                'price' => $totalPrice,
            ]);

            $updatedItem = CartItem::where('id', $item->id)
                ->with('product')
                ->first();

            return response()->json($updatedItem, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error updating cart item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove a specific cart item.
     *
     * @param \App\Models\CartItem $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(CartItem $item)
    {
        try {
            $item->delete();
            return response()->json(['message' => 'Item removed from cart successfully.'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error removing cart item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get the cart with subtotal.
     *
     * @param ShoppingCart $cart
     * @return array
     */
    public function getCartWithSubtotal(ShoppingCart $cart)
    {
        $cart->load('items.product');

        $subtotal = $cart->items->sum('price');

        $cart->subtotal = number_format($subtotal, 2, '.', '');

        return [
            'cart' => $cart,
            'subtotal' => $cart->subtotal,
        ];
    }
}
