<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RecoverFlow OTP</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hello {{ $name }},</p>
    <p>Your RecoverFlow verification code is:</p>
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 4px; margin: 12px 0;">{{ $otp }}</p>
    <p>This code expires in {{ $expiresInMinutes }} minutes.</p>
    <p>If you did not request this, you can ignore this email.</p>
</body>
</html>
