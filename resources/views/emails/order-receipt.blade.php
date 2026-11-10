@extends('emails.layout')

@section('title', 'Payment Receipt — Order '.$reference)

@section('content')
    <h1 style="margin: 0 0 14px 0; font-size: 20px; font-weight: 700; color: #111827; letter-spacing: -0.3px;">
        Purchase Confirmed
    </h1>

    <p style="margin: 0 0 12px 0; font-size: 15px; line-height: 1.6; color: #374151;">
        Hello <strong>{{ $studentName }}</strong>,
    </p>

    <p style="margin: 0 0 22px 0; font-size: 15px; line-height: 1.6; color: #4b5563;">
        Thank you for purchasing on <strong style="color: #111827;">{{ config('app.name', 'Academic Hub') }}</strong>. Your payment has been confirmed and
        <strong>{{ $unlockedCount }} {{ $unlockedCount === 1 ? 'paper has' : 'papers have' }} been unlocked</strong> in your library.
    </p>

    <!-- Order Summary Card -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 22px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #16a34a; border-radius: 6px;">
        <tr>
            <td style="padding: 16px 18px;">
                <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">
                    &#10003; Order Receipt
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 13px; color: #334155;">
                    <tr>
                        <td style="padding: 4px 12px 4px 0; color: #64748b; width: 130px;">Order reference</td>
                        <td style="padding: 4px 0; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #0f172a;">{{ $reference }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 12px 4px 0; color: #64748b;">Date paid</td>
                        <td style="padding: 4px 0; color: #0f172a;">{{ $paidAt }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 12px 4px 0; color: #64748b;">Payment method</td>
                        <td style="padding: 4px 0; color: #0f172a;">{{ $paymentMethod }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 12px 4px 0; color: #64748b;">Total paid</td>
                        <td style="padding: 4px 0; font-size: 14px; font-weight: 800; color: #0b2d5b;">{{ $totalFormatted }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Purchased Bundles -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 26px 0; border: 1px solid #e5e7eb; border-radius: 6px; border-collapse: separate;">
        <tr>
            <td colspan="2" style="padding: 10px 14px; background-color: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280;">
                Unlocked Bundles
            </td>
        </tr>
        @foreach ($items as $item)
            <tr>
                <td style="padding: 12px 14px; border-bottom: {{ $loop->last ? 'none' : '1px solid #f3f4f6' }}; vertical-align: top;">
                    <div style="font-size: 14px; font-weight: 700; color: #111827;">
                        {{ $item['title'] }}
                    </div>
                    @if ($item['meta'])
                        <div style="margin-top: 2px; font-size: 12px; color: #6b7280;">
                            {{ $item['meta'] }}
                        </div>
                    @endif
                </td>
                <td align="right" style="padding: 12px 14px; border-bottom: {{ $loop->last ? 'none' : '1px solid #f3f4f6' }}; vertical-align: top; white-space: nowrap; font-size: 13px; font-weight: 700; color: #111827;">
                    {{ $item['price'] }}
                </td>
            </tr>
        @endforeach
    </table>

    <!-- Primary CTA Button -->
    <table role="presentation" class="btn-table" cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto 24px auto;">
        <tr>
            <td align="center" style="border-radius: 8px; background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); background-color: #1d4ed8;">
                <a href="{{ $libraryUrl }}" class="btn-link" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 14px 32px; font-size: 15px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 8px; letter-spacing: 0.2px;">
                    Open My Library &rarr;
                </a>
            </td>
        </tr>
    </table>

    <!-- Online Receipt Link -->
    <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #6b7280; text-align: center;">
        Need an online copy?
        <a href="{{ $receiptUrl }}" style="color: #2563eb; text-decoration: underline;">View order receipt {{ $reference }}</a>
    </p>
@endsection
