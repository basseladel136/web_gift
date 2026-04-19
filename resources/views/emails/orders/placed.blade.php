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

        .order-info {
            background: #f5f5f5;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f5f5f5;
        }

        .total {
            font-size: 18px;
            font-weight: bold;
            color: #e91e8c;
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
            <p>Thank you for your order, {{ $order->user->name }}!</p>
        </div>

        <div class="order-info">
            <p><strong>Order #:</strong> {{ $order->id }}</p>
            <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
            <p><strong>Date:</strong> {{ $order->created_at->format('d M Y, h:i A') }}</p>
            @if ($order->gift_message)
                <p><strong>Gift Message:</strong> {{ $order->gift_message }}</p>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ $item->unit_price }} EGP</td>
                        <td>{{ round($item->unit_price * $item->quantity, 2) }} EGP</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <br>
        <p>Subtotal: <strong>{{ $order->subtotal }} EGP</strong></p>
        @if ($order->discount > 0)
            <p>Discount: <strong>-{{ $order->discount }} EGP</strong></p>
        @endif
        <p class="total">Total: {{ $order->total }} EGP</p>

        <div class="footer">
            <p>If you have any questions, reply to this email.</p>
            <p>© {{ date('Y') }} Gifts Store. All rights reserved.</p>
        </div>
    </div>
</body>

</html>
