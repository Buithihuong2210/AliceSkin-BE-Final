<?php

namespace App\Http\Controllers;

use App\Mail\PaymentSuccessMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class VNPayController extends Controller
{
    // Tạo yêu cầu thanh toán VNPay
    public function createPayment(Request $request, $order_id)
    {
        try {
            // Xác thực order_id từ URL
            if (!is_numeric($order_id)) {
                return response()->json(['error' => 'Định dạng order_id không hợp lệ. Nó phải là một số nguyên.'], 400);
            }

            // Kiểm tra xem đơn hàng có tồn tại không
            $order = Order::find($order_id);
            if (!$order) {
                return response()->json(['error' => 'Đơn hàng không tồn tại. Vui lòng kiểm tra lại order_id.'], 404);
            }

            // Lấy phương thức thanh toán từ đơn hàng
            $paymentMethod = $order->payment_method;

            // Đặt trạng thái đơn hàng dựa trên phương thức thanh toán
            if ($paymentMethod === 'Cash on Delivery') {
                $order->status = 'Waiting for Delivery';
                $order->save();
                return response()->json(['message' => 'Đơn hàng đã được đặt thành công. Bạn hãy chờ giao hàng'], 200);
            }

            // Nếu là VNpay Payment, tiếp tục với quy trình thanh toán
            if ($paymentMethod === 'VNpay Payment') {
                // Chuyển đổi số tiền sang đơn vị nhỏ nhất
                $amount = $order->total_amount * 100;

                // Kiểm tra số tiền giao dịch
                $minAmount = 10000; // Thay đổi theo quy định của ngân hàng
                $maxAmount = 50000000; // Thay đổi theo quy định của ngân hàng

                if ($amount < $minAmount || $amount > $maxAmount) {
                    return response()->json(['error' => 'Số tiền giao dịch không hợp lệ. Số tiền hợp lệ phải nằm trong khoảng ' . ($minAmount / 100) . ' và ' . ($maxAmount / 100) . ' VND.'], 400);
                }

                // Dữ liệu để gửi
                $inputData = [
                    "vnp_Version" => "2.1.0",
                    "vnp_TmnCode" => env('VNPAY_TMNCODE'),
                    "vnp_Amount" => $amount,
                    "vnp_Command" => "pay",
                    "vnp_CreateDate" => date('YmdHis'),
                    "vnp_CurrCode" => "VND",
                    "vnp_IpAddr" => $request->ip(),
                    "vnp_Locale" => $request->input('locale', 'vn'),
                    "vnp_OrderInfo" => "Thanh toán cho đơn hàng #" . $order->order_id,
                    "vnp_OrderType" => 'billpayment',
                    "vnp_ReturnUrl" => env('VNPAY_RETURN_URL'),
                    "vnp_TxnRef" => $order->order_id,
                ];

                // Nếu có mã ngân hàng, thêm vào dữ liệu
                if ($request->bank_code) {
                    $inputData['vnp_BankCode'] = $request->bank_code;
                }

                // Tạo checksum
                ksort($inputData);
                $hashdata = http_build_query($inputData);
                $vnp_Url = env('VNPAY_URL') . "?" . $hashdata;

                // Tính toán secure hash
                $vnpHashSecret = env('VNPAY_HASH_SECRET');
                if ($vnpHashSecret !== null) {
                    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnpHashSecret);
                    $vnp_Url .= '&vnp_SecureHash=' . $vnpSecureHash;
                }

                // Trả về URL thanh toán
                return response()->json(['payment_url' => $vnp_Url]);
            }

            return response()->json(['error' => 'Phương thức thanh toán không hợp lệ.'], 400);

        } catch (\Exception $e) {
            // Xử lý lỗi
            return response()->json(['error' => 'Đã xảy ra lỗi trong quá trình tạo yêu cầu thanh toán: ' . $e->getMessage()], 500);
        }
    }

    // Xử lý kết quả sau khi thanh toán VNPay
    public function handlePaymentReturn(Request $request)
    {
        // Lấy các tham số từ request
        $transactionNo = $request->input('vnp_TransactionNo');
        $orderId = $request->input('vnp_TxnRef'); // ID đơn hàng
        $responseCode = $request->input('vnp_ResponseCode');
        $payDate = $request->input('vnp_PayDate');
        $amount = $request->input('vnp_Amount'); // Lấy số tiền từ tham số URL

        // Kiểm tra mã phản hồi
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Đơn hàng không tồn tại.'], 404);
        }

        // Kiểm tra mã phản hồi
        if ($responseCode === '00') {
            // Cập nhật trạng thái đơn hàng thành 'Completed'
            DB::table('orders')->where('order_id', $orderId)->update([
                'status' => 'Waiting for Delivery', // Đang chờ xử lý giao hàng
                'payment_status' => 'Paid', // Đã thanh toán
                'updated_at' => now(), // Cập nhật thời gian
            ]);

            // Ghi lại giao dịch vào bảng payments
            DB::table('payments')->insert([
                'order_id' => $orderId, // The order ID
                'transaction_no' => $transactionNo,
                'bank_code' => $request->input('vnp_BankCode'),
                'card_type' => $request->input('vnp_CardType'),
                'pay_date' => now(), // Use current timestamp
                'status' => 'success', // Transaction status
                'amount' => $amount / 100, // Lưu số tiền đã thanh toán (vnp_Amount)
                'created_at' => now(), // Thêm created_at
                'updated_at' => now(), // Thêm updated_at
            ]);

            try {
                Mail::to($order->user->email)->send(new PaymentSuccessMail($order));
            } catch (\Exception $e) {
                // Log lỗi hoặc xử lý theo cách bạn muốn
                Log::error('Failed to send payment success email: ' . $e->getMessage());
            }

            // Tạo tham số cho URL trả về
            $paymentReturnUrl = env('VNPAY_RETURN_URL') . '?' . http_build_query([
                    'vnp_Amount' => $amount,
                    'vnp_BankCode' => $request->input('vnp_BankCode'),
                    'vnp_BankTranNo' => $request->input('vnp_BankTranNo'),
                    'vnp_CardType' => $request->input('vnp_CardType'),
                    'vnp_OrderInfo' => $request->input('vnp_OrderInfo'),
                    'vnp_PayDate' => $payDate,
                    'vnp_ResponseCode' => $responseCode,
                    'vnp_TmnCode' => $request->input('vnp_TmnCode'),
                    'vnp_TransactionNo' => $transactionNo,
                    'vnp_TransactionStatus' => $request->input('vnp_TransactionStatus'),
                    'vnp_TxnRef' => $orderId,
                    // Tính mã hash
                    'vnp_SecureHash' => $this->generateSecureHash($request->all())
                ]);

            // Tạo đường dẫn chi tiết đơn hàng cho frontend
            $orderUrl = url("/order/{$orderId}"); // Trang chi tiết đơn hàng

            return response()->json([
                'message' => 'Payment successful. Order updated.',
                'order_url' => $orderUrl, // Đường dẫn chi tiết đơn hàng
                'payment_return_url' => $paymentReturnUrl // URL trả về đầy đủ
            ], 200);

        } else {
            // Nếu thanh toán thất bại, cập nhật trạng thái đơn hàng
            DB::table('orders')->where('order_id', $orderId)->update([
                'status' => 'Failed',              // Trạng thái thanh toán thất bại
                'payment_status' => 'Failed',      // Trạng thái thanh toán thất bại
                'updated_at' => now(),             // Cập nhật thời gian
            ]);

            // Hủy đơn hàng nếu cả trạng thái đơn hàng và thanh toán đều 'Failed'
            DB::table('orders')->where('order_id', $orderId)->update([
                'status' => 'Canceled',            // Trạng thái đơn hàng bị hủy
                'updated_at' => now(),             // Cập nhật thời gian
            ]);

            return response()->json(['message' => 'Payment failed. Order has been canceled.'], 400);
        }
    }

    public function generateSecureHash($params)
    {
        // Chọn ra các tham số không bao gồm vnp_SecureHash
        $secureHashParams = array_filter($params, function($key) {
            return $key !== 'vnp_SecureHash';
        }, ARRAY_FILTER_USE_KEY);

        // Sắp xếp các tham số theo thứ tự alphabet
        ksort($secureHashParams);

        // Kết nối các tham số theo dạng query string
        $queryString = http_build_query($secureHashParams);

        // Lấy khóa bí mật từ ENV hoặc cấu hình
        $secureHashSecret = env('VNPAY_SECRET_KEY');  // Cần có vnpay_secret_key trong file .env

        // Nối khóa bí mật vào cuối chuỗi query
        $secureString = $queryString . '&' . 'vnp_SecureHashSecret=' . $secureHashSecret;

        // Tính toán hash SHA256 từ chuỗi và trả về
        return strtoupper(hash('sha256', $secureString));
    }

    public function getAllPayments()
    {
        try {
            // Giả sử bạn có một bảng 'payments' lưu trữ tất cả các giao dịch thanh toán
            // Lấy tất cả thông tin từ bảng payments
            $payments = Payment::all();
            // Trả về danh sách các payment dưới dạng JSON
            return response()->json(['payments' => $payments], 200);
        } catch (\Exception $e) {
            // Trả về lỗi nếu có sự cố xảy ra
            return response()->json(['error' => 'Unable to fetch payments: ' . $e->getMessage()], 500);
        }
    }

    public function getTotalPayments()
    {
        try {
            // Tính tổng số tiền đã thanh toán từ bảng payments
            $totalAmount = DB::table('payments')->sum('amount');

            $formattedAmount = number_format($totalAmount, 0) . ' VND';

            return response()->json(['total_amount' => $formattedAmount], 200);        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch total payments: ' . $e->getMessage()], 500);
        }
    }

}