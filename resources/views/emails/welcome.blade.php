<!-- <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to Privatily</title>
</head>
<body>
    <h1>Welcome, {{ $user->name }}!</h1>
    <p>Thank you for registering with Privatily.</p>
    <p>Your account has been successfully created.</p>
    <p>Email: {{ $user->email }}</p>
    <p>Best regards,<br>The Privatily Team</p>
</body>
</html> -->




<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Kimem LLC</title>
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
            border-radius: 5px;
            margin: 20px 0;
        }
        .login-button {
            background-color: #4F46E5;
            color: white !important;
            text-decoration: none !important;
            border-radius: 5px !important;
            margin: 20px 0 !important;
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
        <h1>Welcome to Kimem LLC!</h1>
    </div>
    
    <div class="content">
        <h2>Hello, {{ $user->name }}!</h2>
        
        <p>Thank you for registering with Kimem LLC. Your account has been successfully created.</p>
        
        <p><strong>Account Details:</strong></p>
        <ul>
            <li>Email: {{ $user->email }}</li>
            <li>Name: {{ $user->name }}</li>
        </ul>
        
        <p>You can now start using our services to form your LLC.</p>
        
        <a href="{{ config('app.frontend_url') }}/login" class="button login-button">
            Login to Your Account
        </a>
    </div>
    
    <div class="footer">
        <p>© {{ date('Y') }} Kimem LLC. All rights reserved.</p>
        <p>If you didn't create this account, please ignore this email.</p>
    </div>
</body>
</html>