<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Hello,</p>
    <p>We received a request to reset the password for your account.</p>
    <p>
        <a href="https://yourapp.com/reset-password?token={{ $passwordReset->token }}">
            Click here to reset your password
        </a>
    </p>
    <p style="color: #888; font-size: 0.9em;">
        Note: this link points to a placeholder URL, since this project doesn't yet have a deployed frontend.
        For development/testing purposes, the raw reset token is:
        <code>{{ $passwordReset->token }}</code>
    </p>
    <p>This password reset link will expire on {{ $passwordReset->expires_at->format('F j, Y \a\t g:i A') }}.</p>
    <p>If you didn't request a password reset, you can safely ignore this email — your password will remain unchanged.</p>
</body>
</html>
