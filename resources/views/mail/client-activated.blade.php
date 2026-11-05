<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your account is activated</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f1b2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;color:#6b4ae8;font-weight:600;">{{ config('app.name') }}</p>
                            <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;">Your account is activated</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hello {{ $clientName }}, your client account has been approved and activated. You can now sign in and use your enabled services.</p>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;background:#6b4ae8;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:10px;">Sign in to SysUp360 OmniCast</a>
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#57526b;">Sign in using your registered email address or phone number and your password.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:24px;border-top:1px solid #eceaf3;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#8b8798;">
                                Powered by <a href="https://sysup360.com" style="color:#6b4ae8;text-decoration:none;font-weight:600;">SysUp360</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
