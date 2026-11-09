<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>@yield('title', config('app.name', 'Academic Hub'))</title>
    <!--[if mso]>
    <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; background-color: #f3f4f6; }
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; }
            .content-cell { padding: 24px 18px !important; }
            .header-cell { padding: 20px 18px !important; }
            .btn-table { width: 100% !important; }
            .btn-link { display: block !important; width: 100% !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f3f4f6; width: 100%;">
        <tr>
            <td align="center" style="padding: 30px 12px 40px 12px;">
                <!-- Main Container -->
                <table role="presentation" class="email-container" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; width: 100%; margin: 0 auto;">
                    
                    <!-- Top Brand Bar -->
                    <tr>
                        <td class="header-cell" align="center" style="background: linear-gradient(135deg, #0b2d5b 0%, #1e3a8a 100%); background-color: #0b2d5b; padding: 26px 30px; border-radius: 14px 14px 0 0; border-bottom: 3px solid #eab308;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center">
                                        <div style="font-size: 22px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff; text-decoration: none;">
                                            Academic <span style="color: #60a5fa;">Hub</span>
                                        </div>
                                        <div style="font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #cbd5e1; margin-top: 4px;">
                                            Past Questions · Smarter Preparation
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Card -->
                    <tr>
                        <td class="content-cell" style="background-color: #ffffff; padding: 36px 32px; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; color: #1f2937;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #f9fafb; padding: 22px 24px; border: 1px solid #e5e7eb; border-top: 1px solid #f3f4f6; border-radius: 0 0 14px 14px; text-align: center;">
                            <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 600; color: #4b5563;">
                                Academic Hub — Study smarter, prepare faster.
                            </p>
                            <p style="margin: 0 0 10px 0; font-size: 11px; color: #9ca3af; line-height: 1.4;">
                                Need help? Contact us anytime at <a href="mailto:{{ config('mail.from.address', 'support@amgacademichub.com') }}" style="color: #2563eb; text-decoration: none; font-weight: 500;">{{ config('mail.from.address', 'support@amgacademichub.com') }}</a>
                            </p>
                            <p style="margin: 0; font-size: 10px; color: #9ca3af; line-height: 1.4;">
                                &copy; {{ date('Y') }} Academic Hub. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
