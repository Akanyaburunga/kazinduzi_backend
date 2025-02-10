<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .header {
            background-color: #007bff;
            color: #ffffff;
            padding: 15px;
            font-size: 24px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .logo {
            margin-top: 10px;
            width: 150px;
            height: auto;
        }
        .content {
            padding: 20px;
            font-size: 16px;
            color: #333333;
        }
        .code {
            font-size: 24px;
            font-weight: bold;
            background: #e9ecef;
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            margin: 10px 0;
            color: #007bff;
        }
        .cta-button {
            display: inline-block;
            padding: 12px 20px;
            font-size: 16px;
            color: #ffffff;
            background-color: #28a745;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .cta-button:hover {
            background-color: #218838;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #777777;
        }
        @media (max-width: 600px) {
            .container {
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Logo -->
        <a href="{{ env('APP_URL') }}">
            <img src="{{ env('APP_LOGO') }}" alt="Logo" class="logo">
        </a>

        <!-- Header -->
        <div class="header">{{ env('APP_NAME') }} - Verify Your Email</div>

        <!-- Content -->
        <div class="content">
            <p>Hello,</p>
            <p>Thank you for signing up! To complete your registration, please verify your email by entering the following code:</p>
            <p class="code">{{ $code }}</p>
            <p>This code will expire in <strong>10 minutes</strong>.</p>
            <p>Didn't request this? Ignore this email.</p>
            <a href="{{ env('APP_URL') }}" class="cta-button">Go to {{ env('APP_NAME') }}</a>
        </div>

        <!-- Footer -->
        <div class="footer">
            &copy; {{ date('Y') }} <a href="{{ env('APP_URL') }}">{{ env('APP_NAME') }}</a>. All rights reserved.
        </div>
    </div>
</body>
</html>
