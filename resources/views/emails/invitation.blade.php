<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <p>Hello,</p>
    <p>You've been invited to join <strong>{{ $invitation->company->name }}</strong> as a <strong>{{ $invitation->role }}</strong>.</p>
    <p>
        <a href="https://yourapp.com/accept-invite?token={{ $invitation->token }}">
            Click here to accept this invitation
        </a>
    </p>
    <p style="color: #888; font-size: 0.9em;">
        Note: this link points to a placeholder URL, since this project doesn't yet have a deployed frontend.
        For development/testing purposes, the raw invitation token is:
        <code>{{ $invitation->token }}</code>
    </p>
    <p>This invitation link will expire on {{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}.</p>
    <p>If you weren't expecting this invitation, you can safely ignore this email.</p>
</body>
</html>
