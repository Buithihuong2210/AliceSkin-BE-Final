<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review; // Assuming there is a Review model for handling reviews
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Exception;

class ProductController extends Controller
{
    // List all products with their associated brands and average rating
    public function index()
    {
        try {
            $products = Product::with('brand')->get();

            $products = $products->map(function ($product) {
                return [
                    'product_id' => $product->product_id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'discounted_price' => $product->discounted_price,
                    'quantity' => $product->quantity,
                    'brand_id' => $product->brand_id,
                    'image' => $product->image,
                    'status' => $product->status,
                    'volume' => $product->volume,
                    'nature' => $product->nature,
                    'product_type' => $product->product_type,
                    'main_ingredient' => $product->main_ingredient,
                    'target_skin_type' => $product->target_skin_type,
                    'rating' => $product->rating,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'brand' => $product->brand
                ];
            });

            return response()->json($products, 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while fetching products.', 'error' => $e->getMessage()], 500);
        }
    }
    // Store a new product
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric',
                'discount' => 'nullable|numeric|min:0|max:100',
                'quantity' => 'nullable|numeric|min:0|max:500',
                'brand_id' => 'required|exists:brands,brand_id',
                'image' => 'required|url|ends_with:.jpg,.jpeg,.png,.gif,.svg',
                'volume' => 'nullable|numeric',
                'nature' => 'nullable|string|in:new,best seller,exclusive',
                'product_type' => 'nullable|string|in:Cleanser,Toner,Serum,Moisturizer,Sunscreen,Face Mask,Exfoliator,Treatment Cream,Facial Oil,Eye Cream',
                'main_ingredient' => 'nullable|string|in:Hyaluronic Acid,Vitamin C,Retinol,Salicylic Acid (BHA),Glycolic Acid (AHA),Niacinamide,Ceramides,Peptides,Tea Tree Oil,Aloe Vera',
                'target_skin_type' => 'nullable|string|in:Oily Skin,Dry Skin,Combination Skin,Sensitive Skin,Acne-Prone Skin,Mature Skin,Normal Skin,Dull Skin',
            ]);

            $product_data = $request->all();
            $product_data['status'] = 'available';
            $product_data['images'] = $request->input('image');

            $discount = $request->input('discount', 0);
            $product_data['discounted_price'] = $discount > 0 && $discount < 100
                ? round($product_data['price'] * (1 - $discount / 100), 2)
                : round($product_data['price'], 2);

            $product_data['rating'] = $request->input('rating', 0);

            $product = Product::create($product_data);

            return response()->json($product, 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Please fill in all information', 'error' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while creating the product.', 'error' => $e->getMessage()], 500);
        }
    }

    // Show a specific product by ID
    public function show($product_id)
    {
        try {
            $product = Product::with('brand')->findOrFail($product_id);

            return response()->json([
                'product_id' => $product->product_id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'discount' => $product->discount,
                'discounted_price' => $product->discounted_price,
                'quantity' => $product->quantity,
                'brand_id' => $product->brand_id,
                'image' => $product->image,
                'status' => $product->status,
                'volume' => $product->volume,
                'nature' => $product->nature,
                'rating' => $product->rating,
                'product_type' => $product->product_type,
                'main_ingredient' => $product->main_ingredient,
                'target_skin_type' => $product->target_skin_type,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'brand' => $product->brand
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Product not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while fetching the product.', 'error' => $e->getMessage()], 500);
        }
    }

    // Update a product
    public function update(Request $request, $product_id)
    {
        try {
            $product = Product::findOrFail($product_id);

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|required|numeric',
                'discount' => 'nullable|numeric|min:0|max:100',
                'brand_id' => 'sometimes|required|exists:brands,brand_id',
                'image' => 'sometimes|nullable|url|ends_with:.jpg,.jpeg,.png,.gif,.svg',
                'volume' => 'nullable|numeric',
                'nature' => 'nullable|string|in:new,best seller,exclusive',
                'product_type' => 'nullable|string|in:Cleanser,Toner,Serum,Moisturizer,Sunscreen,Face Mask,Exfoliator,Treatment Cream,Facial Oil,Eye Cream',
                'main_ingredient' => 'nullable|string|in:Hyaluronic Acid,Vitamin C,Retinol,Salicylic Acid (BHA),Glycolic Acid (AHA),Niacinamide,Ceramides,Peptides,Tea Tree Oil,Aloe Vera',
                'target_skin_type' => 'nullable|string|in:Oily Skin,Dry Skin,Combination Skin,Sensitive Skin,Acne-Prone Skin,Mature Skin,Normal Skin,Dull Skin',
            ]);

            $product_data = $request->all();

            if ($request->has('image')) {
                $product_data['image'] = $request->input('image');
            }

            $discount = $request->input('discount', 0);
            $product_data['discounted_price'] = $discount > 0 && $discount < 100
                ? round($product_data['price'] * (1 - $discount / 100), 2)
                : round($product_data['price'], 2);

            if (!$request->has('status')) {
                $product_data['status'] = 'available';
            }

            $product->update($product_data);

            return response()->json([
                'product_id' => $product->product_id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'discount' => $product->discount,
                'discounted_price' => $product->discounted_price,
                'quantity' => $product->quantity,
                'brand_id' => $product->brand_id,
                'image' => $product->image,
                'status' => $product->status,
                'volume' => $product->volume,
                'nature' => $product->nature,
                'rating' => $product->rating,
                'product_type' => $product->product_type,
                'main_ingredient' => $product->main_ingredient,
                'target_skin_type' => $product->target_skin_type,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'brand' => $product->brand
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Product not found'], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the product.', 'error' => $e->getMessage()], 500);
        }
    }

    // Delete a product
    public function destroy($product_id)
    {
        try {
            $product = Product::findOrFail($product_id);
            $product->delete();

            return response()->json(['message' => 'Product deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Product not found'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while deleting the product.', 'error' => $e->getMessage()], 500);
        }
    }

    // Change product status
    public function changeStatus(Request $request, $product_id)
    {
        try {
            $product = Product::findOrFail($product_id);

            $request->validate([
                'status' => 'required|string|in:' . Product::STATUS_AVAILABLE . ',' . Product::STATUS_OUT_OF_STOCK,
            ]);

            $product->status = $request->input('status');
            $product->save();

            return response()->json(['message' => 'Product status updated successfully.', 'product' => $product], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Product not found'], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the product status.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getReviewsByProduct($product_id)
    {
        try {
            $product = Product::find($product_id);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            $reviews = Review::with(['user'])->where('product_id', $product_id)->get();

            $averageRating = $reviews->avg('rate');

            return response()->json([
                'product_name' => $product->name,
                'rating' => round($averageRating, 2),
                'reviews' => $reviews
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve reviews', 'error' => $e->getMessage()], 500);
        }
    }

}