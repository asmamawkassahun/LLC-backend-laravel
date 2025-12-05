<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Summary - {{ $pricingPlanName }} package</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #000;
            padding: 30px;
            background: white;
        }
        
        .header {
            /* display: flex; */
            margin-left: 40px;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            margin-right: 10px;
            background: linear-gradient(135deg, #ec4899, #8b5cf6, #3b82f6);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .logo-text {
            font-size: 18px;
            font-weight: 500;
            color: #333;
            text-transform: lowercase;
        }
        
        .order-info {
            margin-bottom: 30px;
        }
        
        .order-info p {
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .order-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #ff9500;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 8px;
        }
        
        .section {
            margin-bottom: 20px;
            border: 1px solid #e5d4ff;
            border-radius: 4px;
            padding: 15px;
            background-color: #faf5ff;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #9333ea;
            margin-bottom: 12px;
        }
        
        .section-content {
            font-size: 12px;
        }
        
        .section-content p {
            margin-bottom: 8px;
        }
        
        .label {
            color: #666;
        }
        
        .value {
            font-weight: 600;
            color: #333;
        }
        
        .owners-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .owners-table th {
            text-align: left;
            font-weight: 600;
            color: #333;
            padding: 8px;
            border-bottom: 1px solid #e5d4ff;
            font-size: 11px;
        }
        
        .owners-table td {
            padding: 8px;
            border-bottom: 1px solid #e5d4ff;
            font-size: 11px;
        }
        
        .owners-table tr:last-child td {
            border-bottom: none;
        }
        
        .category-tag {
            display: inline-block;
            padding: 2px 8px;
            background-color: #e0e7ff;
            color: #1e40af;
            border: 1px solid #c7d2fe;
            border-radius: 3px;
            font-size: 10px;
            margin-right: 4px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <img src="https://app.privatily.com/assets/img/logo.png" alt="Logo" width="120" height="40">
            </div>
            <!-- <div class="logo-text">privatily</div> -->
        </div>
        
        <!-- Order Information -->
        <div class="order-info">
            <p class="order-title">Order summary of {{ $pricingPlanName }} package</p>
            <p>Country of registration: <strong>{{ $countryName }}</strong></p>
            <p>E-mail: <strong>{{ $userEmail }}</strong></p>
            <p>Order status: <span class="status-badge">{{ $paymentStatus }}</span></p>
        </div>
        
        <!-- The company Section -->
        <div class="section">
            <div class="section-title">The company :</div>
            <div class="section-content">
                <p>
                    <span class="label">Company Name: </span>
                    <span class="value">{{ $companyName }}</span>
                </p>
                <p>
                    <span class="label">Category: </span>
                    @if(count($categoryLabels) > 0)
                        @foreach($categoryLabels as $category)
                            <span class="category-tag">{{ $category }}</span>
                        @endforeach
                    @else
                        <span class="value">N/A</span>
                    @endif
                </p>
            </div>
        </div>
        
        <!-- State Section (Hidden for UK plans) -->
        @if(!$isUKPlan && $stateName)
        <div class="section">
            <div class="section-title">State :</div>
            <div class="section-content">
                <p>
                    <span class="label">State selected: </span>
                    <span class="value">{{ $stateName }}</span>
                </p>
                <p>
                    <span class="label">One time state fees: </span>
                    <span class="value">${{ $stateFee }}</span>
                </p>
            </div>
        </div>
        @endif
        
        <!-- Owners Section -->
        <div class="section">
            <div class="section-title">Owners :</div>
            <div class="section-content">
                @if($owners && $owners->count() > 0)
                    <table class="owners-table">
                        <thead>
                            <tr>
                                <th>Full name</th>
                                <th>Percentage</th>
                                <th>Is a Company</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($owners as $owner)
                                <tr>
                                    <td>{{ $owner->full_name }}</td>
                                    <td>{{ $owner->ownership_percentage }} %</td>
                                    <td>{{ $owner->is_company ? 'Yes' : 'No' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p>No owners specified</p>
                @endif
            </div>
        </div>
        
        <!-- Address Section -->
        <div class="section">
            <div class="section-title">Address</div>
            <div class="section-content">
                <p>{{ $addressString }}</p>
            </div>
        </div>
    </div>
</body>
</html>

