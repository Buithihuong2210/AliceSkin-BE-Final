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
            // Validate the request data
            $request->validate([
                'product_id' => 'required|exists:products,product_id',
                'quantity' => 'required|integer|min:1', // Quantity must be a positive integer
            ]);

            // Fetch the product to check its available quantity (stock)
            $product = Product::findOrFail($request->product_id);

            // Find or create an active shopping cart for the authenticated user
            $cart = ShoppingCart::where('user_id', auth()->id())
                ->where('status', 'active') // Only get active cart
                ->firstOrCreate([
                    'user_id' => auth()->id(),
                    'status' => 'active'
                ]);

            // Check if the item already exists in the cart
            $cartItem = $cart->items()->where('product_id', $request->product_id)->first();

            if ($cartItem) {
                // Calculate the new quantity (existing + new request quantity)
                $newQuantity = $cartItem->quantity + $request->quantity;

                // Check if the new quantity exceeds the available stock
                if ($newQuantity > $product->quantity) {
                    return response()->json([
                        'message' => 'Requested quantity exceeds available stock.',
                        'available_stock' => $product->quantity
                    ], 400);
                }

                // Update the cart item with the new quantity and price based on discounted price
                $cartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $product->discounted_price * $newQuantity // Update the price based on the new quantity
                ]);
            } else {
                // If the item doesn't exist in the cart, add it
                if ($request->quantity > $product->quantity) {
                    return response()->json([
                        'message' => 'Requested quantity exceeds available stock.',
                        'available_stock' => $product->quantity
                    ], 400);
                }

                // Add a new item to the cart
                $cart->items()->create([
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'price' => $product->discounted_price * $request->quantity, // Set price based on discounted price and quantity
                ]);
            }

            // Calculate new subtotal after adding/updating the item
            $subtotal = $cart->items()->sum('price');
            $cart->subtotal = number_format($subtotal, 2, '.', ''); // Format subtotal
            $cart->save(); // Save updated subtotal to the database

            // Reload the cart with updated items and products
            return response()->json($this->getCartWithSubtotal($cart), 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error updating cart: ' . $e->getMessage()], 500);
        }
    }

        public function completeCart()
        {
            try {
                // Find the active shopping cart for the authenticated user
                $cart = ShoppingCart::where('user_id', auth()->id())
                    ->where('status', 'active')
                    ->first();

                // Check if the cart exists
                if (!$cart) {
                    return response()->json(['message' => 'No active cart found.'], 404);
                }

                // Update the cart status to completed after successful payment
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
            // Fetch the user's shopping cart
            $cart = ShoppingCart::with('items.product')->where('user_id', auth()->id())->first();

            // Check if the cart exists
            if (!$cart) {
                return response()->json(['message' => 'Cart not found.'], 404);
            }

            // Find the cart item by ID
            $cartItem = $cart->items()->where('id', $id)->with('product')->first();

            // Check if the cart item exists
            if (!$cartItem) {
                return response()->json(['message' => 'Cart item not found.'], 404);
            }

            // Return the specific cart item
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
            // Validate the request data
            $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            // Fetch the related product to get its discounted price
            $product = Product::findOrFail($item->product_id);

            // Calculate the new total price based on the updated quantity and discounted price
            $totalPrice = $product->discounted_price * $request->quantity;

            // Update the cart item with the new quantity and price
            $item->update([
                'quantity' => $request->quantity,
                'price' => $totalPrice, // Update total price based on discounted price
            ]);

            // Fetch the updated cart item with the related product
            $updatedItem = CartItem::where('id', $item->id)
                ->with('product')
                ->first();

            // Return the updated cart item
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
            // Delete the cart item
            $item->delete();

            // Return a success message
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
        // Load cart items with associated product information
        $cart->load('items.product');

        // Calculate subtotal from cart items using discounted price
        $subtotal = $cart->items->sum('price');

        // Format the subtotal with two decimal places
        $cart->subtotal = number_format($subtotal, 2, '.', '');

        // Return the cart with the formatted subtotal
        return [
            'cart' => $cart,
            'subtotal' => $cart->subtotal,
        ];
    }
}
