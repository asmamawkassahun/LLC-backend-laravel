<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Privatily</title>
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
        .code-box {
            background-color: #fff;
            border: 2px solid #4F46E5;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #4F46E5;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
        .warning {
            background-color: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Password Reset Request</h1>
    </div>
    
    <div class="content">
        <h2>Hello, {{ $userName }}!</h2>
        
        <p>We received a request to reset your password. Use the code below to reset your password:</p>
        
        <div class="code-box">
            <div class="code">{{ $code }}</div>
        </div>
        
        <p>This code will expire in <strong>15 minutes</strong>.</p>
        
        <div class="warning">
            <strong>⚠️ Security Notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.
        </div>
        
        <p>Enter this code on the password reset page to create a new password.</p>
    </div>
    
    <div class="footer">
        <p>© {{ date('Y') }} Privatily. All rights reserved.</p>
        <p>This is an automated message. Please do not reply to this email.</p>
    </div>
</body>
</html>

