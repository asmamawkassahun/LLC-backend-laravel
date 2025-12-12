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
            text-color: white !important;
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
            <p><strong>Service:</strong> {{ $notification->data['service_name'] ?? 'Marketplace Service' }}</p>
            <p><strong>Order Number:</strong> {{ $notification->data['service_order_number'] ?? 'N/A' }}</p>
            @if(isset($notification->data['amount']))
            <p><strong>Amount:</strong> ${{ number_format($notification->data['amount'], 2) }}</p>
            @endif
            @if(isset($notification->data['status']))
            <p><strong>Status:</strong> {{ ucfirst($notification->data['status']) }}</p>
            @endif
        </div>
        
        <p>Your service order is now being processed. We'll notify you once it's completed.</p>
        
        <a href="{{ config('app.frontend_url') }}/dashboard/inbox" class="button">
            View Order Details
        </a>
    </div>
    
    <div class="footer">
        <p>© {{ date('Y') }} Privatily. All rights reserved.</p>
        <p>If you have any questions, please contact our support team.</p>
    </div>
</body>
</html>

