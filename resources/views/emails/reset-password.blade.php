@extends('emails.layout')

@section('title', 'Reset Your Academic Hub Password')

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 700; color: #111827; letter-spacing: -0.3px;">
        Reset Your Password
    </h1>

    <p style="margin: 0 0 14px 0; font-size: 15px; line-height: 1.6; color: #374151;">
        Hello <strong>{{ $name }}</strong>,
    </p>

    <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #4b5563;">
        We received a request to reset the password for your Academic Hub account associated with <strong style="color: #111827;">{{ $email }}</strong>. Click the button below to choose a new password:
    </p>

    <!-- Action Button -->
    <table role="presentation" class="btn-table" cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto 28px auto;">
        <tr>
            <td align="center" style="border-radius: 8px; background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); background-color: #1d4ed8;">
                <a href="{{ $url }}" class="btn-link" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 14px 32px; font-size: 15px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px; letter-spacing: 0.2px;">
                    Reset Password &rarr;
                </a>
            </td>
        </tr>
    </table>

    <!-- Info / Security Box -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #2563eb; border-radius: 6px;">
        <tr>
            <td style="padding: 14px 16px;">
                <p style="margin: 0 0 6px 0; font-size: 13px; font-weight: 600; color: #1e293b;">
                    &#9201; This link will expire in 60 minutes.
                </p>
                <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                    If you did not request a password reset, you can safely ignore this email. No changes will be made to your account.
                </p>
            </td>
        </tr>
    </table>

    <!-- Plaintext URL Fallback -->
    <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #9ca3af; word-break: break-all;">
        If you're having trouble clicking the button, copy and paste this URL into your browser:<br>
        <a href="{{ $url }}" style="color: #2563eb; text-decoration: underline;">{{ $url }}</a>
    </p>
@endsection
