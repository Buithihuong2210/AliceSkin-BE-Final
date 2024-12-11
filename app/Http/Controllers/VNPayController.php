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
// Create VNPay payment request
    public function createPayment(Request $request, $order_id)
    {
        try {
            if (!is_numeric($order_id)) {
                return response()->json(['error' => 'Invalid order_id format. It must be an integer.'], 400);
            }
            $order = Order::find($order_id);
            if (!$order) {
                return response()->json(['error' => 'Order does not exist. Please check order_id again.'], 404);
            }

            $paymentMethod = $order->payment_method;

            if ($paymentMethod === 'Cash on Delivery') {
                $order->status = 'Waiting for Delivery';
                $order->save();
                return response()->json(['message' => 'The order has been placed successfully. Please wait for delivery'], 200);
            }

            if ($paymentMethod === 'VNpay Payment') {
                $amount = $order->total_amount * 100;
                $minAmount = 10000;
                $maxAmount = 200000000;

                if ($amount < $minAmount || $amount > $maxAmount) {
                    return response()->json(['error' => 'Invalid transaction amount. Valid amount must be between '
                        . ($minAmount / 100) . ' and ' . ($maxAmount / 100) . ' VND.'], 400);
                }

                $inputData = [
                    "vnp_Version" => "2.1.0",
                    "vnp_TmnCode" => env('VNPAY_TMNCODE'),
                    "vnp_Amount" => $amount,
                    "vnp_Command" => "pay",
                    "vnp_CreateDate" => date('YmdHis'),
                    "vnp_CurrCode" => "VND",
                    "vnp_IpAddr" => $request->ip(),
                    "vnp_Locale" => $request->input('locale', 'vn'),
                    "vnp_OrderInfo" => "Pay for order #" . $order->order_id,
                    "vnp_OrderType" => 'billpayment',
                    "vnp_ReturnUrl" => env('VNPAY_RETURN_URL'),
                    "vnp_TxnRef" => $order->order_id,
                ];

                if ($request->bank_code) {
                    $inputData['vnp_BankCode'] = $request->bank_code;
                }
                ksort($inputData);
                $hashdata = http_build_query($inputData);
                $vnp_Url = env('VNPAY_URL') . "?" . $hashdata;

                $vnpHashSecret = env('VNPAY_HASH_SECRET');
                if ($vnpHashSecret !== null) {
                    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnpHashSecret);
                    $vnp_Url .= '&vnp_SecureHash=' . $vnpSecureHash;
                }

                return response()->json(['payment_url' => $vnp_Url]);
            }

            return response()->json(['error' => 'Invalid payment method.'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while creating the payment request: ' . $e->getMessage()], 500);
        }
    }

    // Process the result after VNPay payment
    public function handlePaymentReturn(Request $request)
    {
        $transactionNo = $request->input('vnp_TransactionNo');
        $orderId = $request->input('vnp_TxnRef');
        $responseCode = $request->input('vnp_ResponseCode');
        $payDate = $request->input('vnp_PayDate');
        $amount = $request->input('vnp_Amount');

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'The order does not exist.'], 404);
        }
        if ($responseCode === '00') {
            DB::table('orders')->where('order_id', $orderId)->update([
                'status' => 'Waiting for Delivery',
                'payment_status' => 'Paid',
                'updated_at' => now(),
            ]);

            DB::table('payments')->insert([
                'order_id' => $orderId,
                'transaction_no' => $transactionNo,
                'bank_code' => $request->input('vnp_BankCode'),
                'card_type' => $request->input('vnp_CardType'),
                'pay_date' => now(),
                'status' => 'success',
                'amount' => $amount / 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                Mail::to($order->user->email)->send(new PaymentSuccessMail($order));
            } catch (\Exception $e) {
                Log::error('Failed to send payment success email: ' . $e->getMessage());
            }

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
                    'vnp_SecureHash' => $this->generateSecureHash($request->all())
                ]);

            $orderUrl = url("/order/{$orderId}");

            return response()->json([
                'message' => 'Payment successful. Order updated.',
                'order_url' => $orderUrl,
                'payment_return_url' => $paymentReturnUrl
            ], 200);

        } else {
            DB::table('orders')->where('order_id', $orderId)->update([
                'status' => 'Failed',
                'payment_status' => 'Failed',
                'updated_at' => now(),
            ]);

            DB::table('orders')->where('order_id', $orderId)->update([
                'status' => 'Canceled',
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Payment failed. Order has been canceled.'], 400);
        }
    }

    public function generateSecureHash($params)
    {
        $secureHashParams = array_filter($params, function($key) {
            return $key !== 'vnp_SecureHash';
        }, ARRAY_FILTER_USE_KEY);

        ksort($secureHashParams);

        $queryString = http_build_query($secureHashParams);

        $secureHashSecret = env('VNPAY_SECRET_KEY');

        $secureString = $queryString . '&' . 'vnp_SecureHashSecret=' . $secureHashSecret;

        return strtoupper(hash('sha256', $secureString));
    }

    public function getAllPayments()
    {
        try {
            $payments = Payment::all();
            return response()->json(['payments' => $payments], 200);
        } catch (\Exception $e) {
            // Trả về lỗi nếu có sự cố xảy ra
            return response()->json(['error' => 'Unable to fetch payments: ' . $e->getMessage()], 500);
        }
    }

    public function getTotalPayments()
    {
        try {
            $totalAmount = DB::table('payments')->sum('amount');

            $formattedAmount = number_format($totalAmount, 0) . ' VND';

            return response()->json(['total_amount' => $formattedAmount], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch total payments: ' . $e->getMessage()], 500);
        }
    }

}