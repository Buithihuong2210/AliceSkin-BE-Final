<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

use Illuminate\Support\Facades\Date;
use Carbon\Carbon;


class VoucherController extends Controller
{
    // Lấy tất cả các voucher
    public function index()
    {
        try {
            $this->checkVoucherExpiry();

            $vouchers = Voucher::all();

            return response()->json($vouchers, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Không thể lấy danh sách voucher'], 500);
        }
    }


    // Tạo voucher mới
    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|unique:vouchers,code',
                'discount_amount' => 'required|numeric',
                'start_date' => 'required|date',
                'expiry_date' => 'required|date|after_or_equal:start_date',
            ]);

            $currentDate = Carbon::now();
            $status = ($request->expiry_date >= $currentDate) ? 'active' : 'inactive';

            $voucher = Voucher::create(array_merge($request->all(), ['status' => $status]));

            return response()->json($voucher, 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => 'Không thể tạo voucher'], 500);
        }
    }

    // Kiểm tra voucher đã hết hạn và cập nhật trạng thái
    private function checkVoucherExpiry()
    {
        $currentDate = Carbon::now();

        $expiredVouchers = Voucher::where('status', 'active')
            ->where('expiry_date', '<', $currentDate)
            ->get();

        foreach ($expiredVouchers as $voucher) {
            $voucher->status = 'inactive';
            $voucher->save();
        }
    }


    //show detail
    public function show($voucher_id)
    {
        try {
            $voucher = Voucher::findOrFail($voucher_id);

            return response()->json($voucher, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Voucher không tồn tại'], 404);
        }
    }


    // Update a voucher
    public function update(Request $request, $voucher_id)
    {
        try {
            $voucher = Voucher::findOrFail($voucher_id);

            $request->validate([
                'code' => 'required|unique:vouchers,code,' . $voucher_id . ',voucher_id',
                'discount_amount' => 'required|numeric',
                'start_date' => 'required|date',
                'expiry_date' => 'required|date|after_or_equal:start_date',
            ]);

            $currentDate = Carbon::now();
            $status = ($request->expiry_date >= $currentDate) ? 'active' : 'inactive';

            $voucher->update(array_merge($request->all(), ['status' => $status]));

            return response()->json($voucher, 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => 'Không thể cập nhật voucher'], 500);
        }
    }

    // Change voucher status
    public function changeStatus(Request $request, $voucher_id)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive',
            ]);

            $voucher = Voucher::findOrFail($voucher_id);

            $voucher->status = $request->input('status');
            $voucher->save();

            return response()->json([
                'message' => 'Trạng thái voucher đã được cập nhật thành công',
                'voucher' => $voucher,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Không thể thay đổi trạng thái voucher',
            ], 500);
        }
    }

    // Delete a voucher
    public function destroy($voucher_id)
    {
        try {
            $voucher = Voucher::findOrFail($voucher_id);
            $voucher->delete();

            return response()->json(['message' => 'Voucher deleted successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete voucher'], 500);
        }
    }
}