<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
            background-color: #f9fafb;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            text: white !important;
            border-radius: 5px;
            margin: 20px 0;
        }
        .order-details {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #4F46E5;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $notification->title }}</h1>
    </div>
    
    <div class="content">
        <h2>Hello, {{ $notification->user->name }}!</h2>
        
        <p>{{ $notification->message }}</p>
        
        <div class="order-details">
            <p><strong>Order Number:</strong> {{ $notification->data['order_number'] ?? 'N/A' }}</p>
            @if(isset($notification->data['total_amount']))
            <p><strong>Total Amount:</strong> ${{ number_format($notification->data['total_amount'], 2) }}</p>
            @endif
        </div>
        
        <p>You can view your order details and track its progress in your dashboard.</p>
        
        <a href="{{ config('app.frontend_url') }}/orders/{{ $notification->data['order_id'] ?? '' }}" class="button">
            View Order
        </a>
    </div>
    
    <div class="footer">
        <p>© {{ date('Y') }} Privatily. All rights reserved.</p>
        <p>If you have any questions, please contact our support team.</p>
    </div>
</body>
</html>

