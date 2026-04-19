<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            border-radius: 8px;
            padding: 32px;
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .header h1 {
            color: #e91e8c;
            font-size: 24px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 16px;
            background: #e91e8c;
            color: #fff;
        }

        .order-info {
            background: #f5f5f5;
            border-radius: 6px;
            padding: 16px;
            margin: 24px 0;
        }

        .footer {
            text-align: center;
            margin-top: 32px;
            color: #999;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🎁 Gifts Store</h1>
            <p>Hi {{ $order->user->name }}, your order status has been updated.</p>
        </div>

        <div style="text-align:center; margin: 24px 0;">
            <span class="status-badge">{{ ucfirst($order->status) }}</span>
        </div>

        <div class="order-info">
            <p><strong>Order #:</strong> {{ $order->id }}</p>
            <p><strong>Total:</strong> {{ $order->total }} EGP</p>
            <p><strong>Updated at:</strong> {{ $order->updated_at->format('d M Y, h:i A') }}</p>
        </div>

        @if ($order->status === 'shipped')
            <p style="text-align:center;">📦 Your order is on its way! You will receive it soon.</p>
        @elseif($order->status === 'delivered')
            <p style="text-align:center;">✅ Your order has been delivered. Enjoy your gift!</p>
        @elseif($order->status === 'cancelled')
            <p style="text-align:center;">❌ Your order has been cancelled. Contact us if you have questions.</p>
        @endif

        <div class="footer">
            <p>© {{ date('Y') }} Gifts Store. All rights reserved.</p>
        </div>
    </div>
</body>

</html>
