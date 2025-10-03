<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Code</title>
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
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .code {
            background-color: #fff;
            border: 2px dashed #4CAF50;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Anjem Driver Verification</h1>
    </div>
    <div class="content">
        <h2>Email Verification Code</h2>
        <p>Thank you for registering as a driver with Anjem!</p>
        <p>Please use the verification code below to complete your registration:</p>

        <div class="code">
            {{ $code }}
        </div>

        <p><strong>This code will expire in {{ $expiresInMinutes }} minutes.</strong></p>

        <p>If you didn't request this code, please ignore this email.</p>

        <div class="footer">
            <p>© {{ date('Y') }} Anjem - Campus Ride-sharing Platform</p>
            <p>This is an automated email. Please do not reply.</p>
        </div>
    </div>
</body>
</html>
