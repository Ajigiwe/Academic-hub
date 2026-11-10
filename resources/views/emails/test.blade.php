@extends('emails.layout')

@section('title', 'Email Delivery Test — Academic Hub')

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 700; color: #111827; letter-spacing: -0.3px;">
        SMTP Delivery Confirmed
    </h1>

    <p style="margin: 0 0 14px 0; font-size: 15px; line-height: 1.6; color: #374151;">
        Hello <strong>{{ $name ?? 'Admin' }}</strong>,
    </p>

    <p style="margin: 0 0 22px 0; font-size: 15px; line-height: 1.6; color: #4b5563;">
        This is a test email from <strong style="color: #111827;">Academic Hub</strong>. Seeing this message in your inbox confirms that your server's email configuration can send branded transactional emails — including password reset links — to students and staff.
    </p>

    <!-- Diagnostics Summary Box -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 26px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #16a34a; border-radius: 6px;">
        <tr>
            <td style="padding: 16px 18px;">
                <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">
                    &#10003; Active Mail Configuration
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 13px; color: #334155;">
                    <tr>
                        <td style="padding: 4px 12px 4px 0; color: #64748b; width: 130px;">Mailer driver</td>
                        <td style="padding: 4px 0; font-weight: 600; color: #0f172a;">{{ $mailer ?? 'smtp' }}</td>
                    </tr>
                    @if (! empty($host))
                        <tr>
                            <td style="padding: 4px 12px 4px 0; color: #64748b;">SMTP server</td>
                            <td style="padding: 4px 0; font-weight: 600; color: #0f172a;">{{ $host }}:{{ $port ?? '' }} ({{ $scheme ?? 'auto' }})</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 4px 12px 4px 0; color: #64748b;">From address</td>
                        <td style="padding: 4px 0; font-weight: 600; color: #0f172a;">{{ $from ?? '' }}</td>
                    </tr>
                    @if (! empty($triggeredBy))
                        <tr>
                            <td style="padding: 4px 12px 4px 0; color: #64748b;">Triggered by</td>
                            <td style="padding: 4px 0; color: #0f172a;">{{ $triggeredBy }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 4px 12px 4px 0; color: #64748b;">Sent at (UTC)</td>
                        <td style="padding: 4px 0; color: #0f172a;">{{ $sentAt ?? now()->toDateTimeString() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Action Button -->
    <table role="presentation" class="btn-table" cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td align="center" style="border-radius: 8px; background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); background-color: #1d4ed8;">
                <a href="{{ $actionUrl ?? url('/admin/settings') }}" class="btn-link" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 13px 28px; font-size: 14px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px; letter-spacing: 0.2px;">
                    Open Admin Settings &rarr;
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #9ca3af;">
        You can safely delete or archive this test message. No further action is required.
    </p>
@endsection

