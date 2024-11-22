<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
</head>
<body>
<h1>Thank You for Your Payment!</h1>
<p>Dear {{ $order->user_name }},</p>
<p>Your payment for Order #{{ $order->order_id }} has been successfully processed.</p>
<p><strong>Total Amount Paid:</strong> {{ number_format($order->total_amount, 0) }} VND</p>
<p>We will start processing your order soon.</p>
<p>Thank you for shopping with us!</p>
</body>
</html>
