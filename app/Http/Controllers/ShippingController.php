<?php

namespace App\Http\Controllers;

use App\Models\Shipping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingController extends Controller
{
    // Get all shipping records
    public function index()
    {
        try {
            $shippings = Shipping::all();
            return response()->json($shippings, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving shipping records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Get a specific shipping record
    public function show($shipping_id)
    {
        try {
            $shipping = Shipping::findOrFail($shipping_id);
            return response()->json($shipping, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Shipping not found.',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    // Create a new shipping record
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:shippings,name',
                'shipping_amount' => 'required|numeric',
                'method' => 'required|string',

            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $shipping = Shipping::create($request->all());
            return response()->json($shipping, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the shipping record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a shipping record
    public function update(Request $request, $shipping_id)
    {
        try {
            $shipping = Shipping::findOrFail($shipping_id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:shippings,name,' . $shipping->id,
                'shipping_amount' => 'required|numeric',
                'method' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $shipping->update($request->all());
            return response()->json($shipping, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the shipping record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete a shipping record
    public function destroy($shipping_id)
    {
        try {
            $shipping = Shipping::findOrFail($shipping_id);

            $shipping->delete();

            return response()->json(['message' => "Shipping {$shipping_id} deleted successfully"], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Shipping not found.',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the shipping record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
